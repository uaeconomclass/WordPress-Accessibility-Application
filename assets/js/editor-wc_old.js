/* Web Component UI for Accessibility Auditor (editor-wc.js)
   - Injects axe-core into preview iframe (if same-origin)
   - Renders results, highlights preview elements and posts selection to Bricks
*/

class AADashboard extends HTMLElement {
  constructor() {
    super();
    this.attachShadow({ mode: "open" });

    // memory for saved results (violations array)
    this.savedResults = [];

     
    if (window.aaEditor?.results) {

        // this.savedResults = aaEditor.results?.violations || [];

        // console.log(this.savedResults);

      try {
        const parsed = typeof aaEditor.results === "string" ? JSON.parse(aaEditor.results) : aaEditor.results;
        this.savedResults = parsed?.violations || [];
      } catch (e) {
        console.warn("Failed to parse saved results:", e);
      }
    }

    // store currently highlighted preview element for cleanup
    this._previewHighlight = null;

    // render initial floating button
    this.renderButton();
  }

  /*****************************************************************
   * Utilities
   *****************************************************************/
  escapeHTML(str) {
    if (str == null) return "";
    return String(str).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
                      .replace(/"/g, "&quot;").replace(/'/g, "&#39;");
  }

  safeJSON(obj) {
    try { return JSON.stringify(obj); } catch(e){ return "[]"; }
  }

  /*****************************************************************
   * Render Floating Button
   *****************************************************************/
  renderButton() {
    this.shadowRoot.innerHTML = `
      <style>
        #aa-accessibility-btn {
          position: fixed;
          bottom: 10px;
          right: 50px;
          height: 35px;
          width: 35px;
          z-index: 9999;
          font-weight: 700;
          font-size: 16px;
          padding: 0;
          color: white;
          background: hsla(193, 12%, 20%, 1);
          display: grid;
          place-items: center;
          border-radius: 999px;
          border: 1px solid rgba(255,255,255,0.08);
          box-shadow: 0 0 30px -2px rgba(0,0,0,0.4);
          cursor: pointer;
        }
        .aa-status-dot {
          position: absolute;
          top: -4px;
          right: -2px;
          width: 10px;
          height: 10px;
          border-radius: 999px;
          border: 1px solid rgba(255,255,255,0.12);
          background: #FD8E03;
          transition: background 0.3s ease;
        }
      </style>

      <button id="aa-accessibility-btn" title="Accessibility Auditor" aria-label="Open Accessibility Auditor">
        <svg width="15" height="20" viewBox="0 0 15 20" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M5.22307 1.925C5.22307 0.862813 6.08588 0 7.14807 0C8.21026 0 9.07307 0.862813 9.07307 1.925C9.07307 2.98719 8.21026 3.85 7.14807 3.85C6.08588 3.85 5.22307 2.98719 5.22307 1.925ZM0.173384 3.25531C0.499946 2.74312 1.18057 2.59531 1.69276 2.92188L4.50807 4.72656C5.29526 5.23187 6.21307 5.5 7.14807 5.5C8.08307 5.5 9.00088 5.23187 9.78807 4.72656L12.6034 2.92188C13.1156 2.59531 13.7962 2.74312 14.1228 3.25531C14.4493 3.7675 14.3015 4.44812 13.7893 4.77469L10.974 6.57937C10.6303 6.79938 10.2693 6.98844 9.89463 7.14656V10.9381C9.89463 11.5775 10.0081 12.2169 10.2246 12.8184L10.3896 13.2722V13.2756L12.0259 17.7753C12.2081 18.2738 12.0053 18.8203 11.5687 19.0884C11.5068 19.1262 11.4381 19.1606 11.3693 19.1847C10.8709 19.3669 10.3243 19.1641 10.0562 18.7275C10.0184 18.6656 9.98401 18.5969 9.95995 18.5281L8.3237 14.0284V14.025C8.14151 13.53 7.67057 13.2 7.14463 13.2C6.61526 13.2 6.14432 13.53 5.96557 14.025L4.33276 18.5247C4.13682 19.0609 3.56963 19.3531 3.02995 19.2156C2.99557 19.2053 2.95776 19.195 2.92338 19.1812C2.35276 18.975 2.05713 18.3425 2.26682 17.7719L3.90307 13.2722L4.06807 12.815C4.28807 12.2134 4.39807 11.5775 4.39807 10.9347V7.14313C4.02338 6.985 3.66588 6.79594 3.32213 6.57594L0.506821 4.77469C-0.00536631 4.44812 -0.153179 3.7675 0.173384 3.25531Z" fill="white"/>
        </svg>
        <span class="aa-status-dot" id="aa-status-dot"></span>
      </button>
    `;

    const btn = this.shadowRoot.querySelector("#aa-accessibility-btn");
    btn.addEventListener("click", () => this.renderPanel());
  }

  /*****************************************************************
   * Render Panel (main UI in shadow DOM)
   *****************************************************************/
  renderPanel() {
    // Panel HTML + CSS
    this.shadowRoot.innerHTML = `
      <style>
        :host { all: initial; }
        .aa-panel {
          width: 400px;
          background: #16191B;
          color: #fff;
          font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, Arial;
          position: fixed;
          top: 0;
          right: 0;
          height: 100vh;
          display: flex;
          flex-direction: column;
          box-shadow: -2px 0 10px rgba(0,0,0,0.6);
          z-index: 99999;
          border-left: 1px solid rgba(255,255,255,0.06);
          -webkit-font-smoothing:antialiased;
        }

       
        
        .aa-panel-header {
          display: flex;
          align-items: center;
          gap: 12px;
          padding: 1rem;
          border-bottom: 1px solid #222;

          
        }

        
        .aa-panel-header h2 { font-weight:700; font-size:15px; text-transform:uppercase; margin:0; }
        .aa-pane-score { margin-left:auto; display:flex; align-items:center; gap:6px; font-weight:500; font-size:12px; }
        .aa-score { display:inline-block; border:1px solid #754521; width:26px; height:18px; text-align:center; border-radius:3px; color:#ffb978; }
        #aa-close { margin-left:8px; width:28px; height:28px; border-radius:6px; border:none; background:transparent; color:#DCE0E4; cursor:pointer; }
        .aa-panel-body { padding: 10px 12px 16px; display:flex; flex-direction:column; gap:12px; height: calc(100vh - 88px); }
        .aa-btn { padding:10px 12px; background:#0073e6; border:none; color:white; border-radius:8px; cursor:pointer; font-weight:600;}
        .aa-btn:hover { background:#005bb5; }

        /* results list */
        .aa-pane-content { padding:8px; overflow:auto; flex:1; }
        .aa-issues-list { list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:12px; }
        .aa-issue-item { background:#2a2c31; border-radius:10px; border:1px solid rgba(255,255,255,0.06); overflow:hidden; transition:background 0.18s, box-shadow 0.18s; }
        .aa-issue-item.aa-open { background:#33353a; box-shadow:0 4px 12px rgba(0,0,0,0.45); }
        .aa-issue-button { display:flex; align-items:center; justify-content:space-between; width:100%; padding:14px 16px; background:transparent; border:0; color:#DCE0E4; cursor:pointer; text-align:left; }
        .aa-left { display:flex; align-items:center; gap:12px; }
        .aa-severity-dot { width:10px; height:10px; border-radius:999px; flex-shrink:0; }
        .aa-issue-title { font-weight:700; font-size:14px; letter-spacing:0.4px; text-color:#E6E9ED; text-transform:uppercase;}
        .aa-chevron { font-size:18px; color:#DCE0E4; transition:transform 0.18s; }
        .aa-issue-item.aa-open .aa-chevron { transform:rotate(90deg); }

        /* severity colors */
        .critical .aa-severity-dot, .aa-issue-item.critical .aa-severity-dot { background:#ff3b30; }
        .serious  .aa-severity-dot, .aa-issue-item.serious .aa-severity-dot { background:#ff9500; }
        .moderate .aa-severity-dot, .aa-issue-item.moderate .aa-severity-dot { background:#ffd60a; }
        .minor    .aa-severity-dot, .aa-issue-item.minor .aa-severity-dot { background:#5ac8fa; }

        /* details */
        .aa-issue-details { max-height:0; overflow:hidden; padding:0 16px; color:#9aa3ad; font-size:13px; transition:max-height .25s ease, padding .25s ease; border-top:1px solid rgba(255,255,255,0.04); }
        .aa-issue-item.aa-open .aa-issue-details { max-height:600px; padding:12px 16px; }

        /* small helpers */
        .aa-no-issues { padding:16px; color:#bfc6cc; }
        .aa-small { font-size:12px; color:#9aa3ad; }

        .aa-issue-button:focus { outline:2px solid rgba(255,149,0,0.28); outline-offset:2px; border-radius:6px; }
      </style>

      <div class="aa-panel" role="dialog" aria-label="Accessibility Auditor">
        <div class="aa-panel-header">
          <h2>Accessibility</h2>
          <div class="aa-pane-score">Page score: <span class="aa-score">D</span></div>
          <button id="aa-close" aria-label="Close panel">✕</button>
        </div>

        <div class="aa-panel-body">
          <button id="aa-run-scan" class="aa-btn">🔍 Run Accessibility Scan</button>
          <div class="aa-pane-content">
            <div id="aa-scan-results" class="aa-results">
              <p class="aa-no-issues"><em>No scan run yet.</em></p>
            </div>
          </div>
        </div>
      </div>
    `;

    // events
    this.shadowRoot.querySelector("#aa-close").addEventListener("click", () => this.renderButton());
    this.shadowRoot.querySelector("#aa-run-scan").addEventListener("click", () => this.runScan());

    // preload saved results if present
    if (this.savedResults && this.savedResults.length) {
      this.renderResults(this.savedResults);
    } else if (window.aaEditor?.needsScan) {
      // optionally auto-run
      // this.runScan();
    }
  }


  /*****************************************************************
 * Render Results (receives violations array or axe results violations)
 *****************************************************************/
renderResults(violations = []) {
  console.log(violations);
  const container = this.shadowRoot.querySelector("#aa-scan-results");
  if (!container) return;

  if (!Array.isArray(violations) || violations.length === 0) {
    container.innerHTML = `<p class="aa-no-issues"><em>No issues found 🎉</em></p>`;
    return;
  }

  // build list HTML
  let html = `<ul class="aa-issues-list">`;
  violations.forEach((issue, idx) => {
    const impact = (issue.impact || "minor").toLowerCase();
    const severity = ["critical","serious","moderate","minor"].includes(impact) ? impact : "minor";
    const title = this.escapeHTML(issue.help || issue.id || "Untitled issue");
    const desc = this.escapeHTML(issue.description || "");
    const targets = (issue.nodes || []).map(n => n.target || []);
    const firstTarget = targets[0]?.[0] || "";

    html += `
      <li class="aa-issue-item ${severity}" data-index="${idx}" data-targets='${this.safeJSON(targets)}'>
        <button type="button" class="aa-issue-button" aria-expanded="false">
          <span class="aa-left">
            <span class="aa-severity-dot" aria-hidden="true"></span>
            <span class="aa-issue-title">${title}</span>
          </span>
          <span class="aa-chevron" aria-hidden="true">›</span>
        </button>

        <div class="aa-issue-details">
          <p>${desc || `<span class="aa-small">No description provided.</span>`} 
             You can learn more about this type of <a href="${issue.helpUrl}" target="_blank">issue by clicking here</a></p>

          <div class="aa-actions">
              <button class="aa-btn-steps" data-issue-id="${issue.id}">📄 Generate Fix Guide</button>
              <button class="aa-btn-ai-fix" data-issue-id="${issue.id}">🤖 Auto-Fix with AI</button>
              <button class="aa-btn-save" data-issue-id="${issue.id}">💾 Save Changes</button>
          </div>
          <div class="aa-fix-output" id="aa-fix-output-${issue.id}"></div>
        </div>
      </li>
    `;
  });
  html += `</ul>`;
  container.innerHTML = html;

  // reset old handlers
  if (this._resultsClickHandler) this.shadowRoot.removeEventListener("click", this._resultsClickHandler);
  if (this._resultsMouseOverHandler) this.shadowRoot.removeEventListener("mouseover", this._resultsMouseOverHandler);
  if (this._resultsMouseOutHandler) this.shadowRoot.removeEventListener("mouseout", this._resultsMouseOutHandler);

  /*******************
   * Event Handlers
   *******************/
  this._resultsClickHandler = async (e) => {
    // Expand/Collapse
    const btn = e.target.closest(".aa-issue-button");
    if (btn) {
      const li = btn.closest(".aa-issue-item");
      if (!li) return;
      const expanded = li.classList.toggle("aa-open");
      btn.setAttribute("aria-expanded", expanded ? "true" : "false");
      if (expanded) this._onIssueOpen(li);
      else this._clearPreviewHighlight();
      return;
    }

    // Guided Fix button
    const stepsBtn = e.target.closest(".aa-btn-steps");
    if (stepsBtn) {
      const issueId = stepsBtn.dataset.issueId;
      const output = this.shadowRoot.querySelector(`#aa-fix-output-${issueId}`);
      output.innerHTML = `<p class="aa-loading">Generating guided steps…</p>`;
      const issue = violations.find(v => v.id === issueId);
      const steps = await this._generateGuidedFix(issue);
      output.innerHTML = `<pre class="aa-guided-steps">${this.escapeHTML(steps)}</pre>`;
      return;
    }

    // Auto-Fix button
    const aiBtn = e.target.closest(".aa-btn-ai-fix");
    if (aiBtn) {
      const issueId = aiBtn.dataset.issueId;
      const output = this.shadowRoot.querySelector(`#aa-fix-output-${issueId}`);
      output.innerHTML = `<p class="aa-loading">Applying AI fix…</p>`;
      const issue = violations.find(v => v.id === issueId);
      const result = await this._applyAutoFix(issue);
      output.innerHTML = `<pre class="aa-ai-result">${this.escapeHTML(JSON.stringify(result,null,2))}</pre>`;
      return;
    }

    // Save button
    const saveBtn = e.target.closest(".aa-btn-save");
    if (saveBtn) {
      const issueId = saveBtn.dataset.issueId;
      const output = this.shadowRoot.querySelector(`#aa-fix-output-${issueId}`);
      output.innerHTML = `<p class="aa-loading">Saving changes…</p>`;
      const issue = violations.find(v => v.id === issueId);
      const res = await this._saveFix(issue);
      output.innerHTML = `<p class="aa-success">✅ Saved fix for ${issueId}</p>`;
      return;
    }
  };

  this._resultsMouseOverHandler = (e) => {
    const item = e.target.closest(".aa-issue-item");
    if (!item) return;
    this._previewHighlightFromItem(item);
  };
  this._resultsMouseOutHandler = (e) => {
    const item = e.target.closest(".aa-issue-item");
    if (!item) return;
    this._clearPreviewHighlight();
  };

  this.shadowRoot.addEventListener("click", this._resultsClickHandler);
  this.shadowRoot.addEventListener("mouseover", this._resultsMouseOverHandler);
  this.shadowRoot.addEventListener("mouseout", this._resultsMouseOutHandler);
}


/*****************************************************************
 * Generate Guided Fixes (Claude → step-by-step WCAG guidance)
 *****************************************************************/
async _generateGuidedFix(issue) {
  try {
    // Construct context for Claude
    const context = {
      issueId: issue.id,
      description: issue.description,
      help: issue.help,
      helpUrl: issue.helpUrl,
      impact: issue.impact,
      nodes: issue.nodes?.map(n => n.target) || [],
      builder: "Bricks Builder",
      framework: "AutomaticCSS"
    };

    // Call your backend AI endpoint
    const resp = await fetch(`${aaEditor.root}guided-fix`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": aaEditor.restNonce,
      },
      body: JSON.stringify({ context })
    });

    if (!resp.ok) throw new Error(`Server returned ${resp.status}`);
    const data = await resp.json();

    // Claude should return a plain text / markdown string with steps
    return data.steps || "⚠️ No guidance returned from AI.";
  } catch (err) {
    console.error("GuidedFix error:", err);
    return `❌ Error generating fix guide: ${err.message}`;
  }
}

/*****************************************************************
 * Apply Auto-Fix (Claude → JSON patch, Bricks API → apply changes)
 *****************************************************************/
async _applyAutoFix(issue) {
  try {
    // const resp = await fetch("/wp-json/aa/v1/auto-fix", {
    //   method: "POST",
    //   headers: { "Content-Type": "application/json" },
    //   body: JSON.stringify({ issue })
    // });


    const resp = await fetch(`${aaEditor.root}auto-fix`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": aaEditor.restNonce,
      },
       body: JSON.stringify({ issue })
    });

    if (!resp.ok) throw new Error(`Server returned ${resp.status}`);
    const data = await resp.json();

    // Expect JSON describing Bricks element changes:
    // {
    //   "changes": [
    //     { "elementId": "brxe-123", "setting": "ariaLabel", "before": "", "after": "Main Navigation" },
    //     { "elementId": "brxe-456", "setting": "class", "before": "", "after": "color-contrast" }
    //   ],
    //   "changelog": [...]
    // }

    // Apply each change via Bricks API/REST
    if (data.changes && Array.isArray(data.changes)) {
      for (const change of data.changes) {
        await this._applyBricksChange(change);
      }
    }

    return data;
  } catch (err) {
    console.error("AutoFix error:", err);
    return { error: err.message };
  }
}

/*****************************************************************
 * Save Fix (commit via Bricks API + revision entry)
 *****************************************************************/
async _saveFix(issue) {
  try {
    const resp = await fetch("/wp-json/aa/v1/save-fix", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ issue })
    });

    if (!resp.ok) throw new Error(`Server returned ${resp.status}`);
    const data = await resp.json();

    // Backend should handle:
    // - Saving Bricks revision
    // - Logging changelog
    // - Returning { success: true }
    return data;
  } catch (err) {
    console.error("SaveFix error:", err);
    return { error: err.message };
  }
}

/*****************************************************************
 * Helper: Apply a single Bricks change (local)
 *****************************************************************/
async _applyBricksChange(change) {
  try {
    // Example: Update via Bricks REST API
    const resp = await fetch(`/wp-json/bricks/v1/elements/${change.elementId}`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        [change.setting]: change.after
      })
    });

    if (!resp.ok) throw new Error(`Bricks update failed: ${resp.status}`);
    return await resp.json();
  } catch (err) {
    console.error("BricksChange error:", err, change);
    return null;
  }
}


//   /*****************************************************************
//    * Render Results (receives violations array or axe results violations)
//    *****************************************************************/
// //   renderResults(violations = []) {

// //     console.log(violations);
// //     const container = this.shadowRoot.querySelector("#aa-scan-results");
// //     if (!container) return;

// //     if (!Array.isArray(violations) || violations.length === 0) {
// //       container.innerHTML = `<p class="aa-no-issues"><em>No issues found 🎉</em></p>`;
// //       return;
// //     }

// //     // build list HTML
// //     let html = `<ul class="aa-issues-list">`;
// //     violations.forEach((issue, idx) => {
// //       const impact = (issue.impact || "minor").toLowerCase();
// //       const severity = ["critical","serious","moderate","minor"].includes(impact) ? impact : "minor";
// //       const title = this.escapeHTML(issue.help || issue.id || "Untitled issue");
// //       const desc = this.escapeHTML(issue.description || "");
// //       // gather selectors for nodes (array of arrays)
// //       const targets = (issue.nodes || []).map(n => n.target || []);
// //       const firstTarget = targets[0]?.[0] || "";
// //       html += `
// //         <li class="aa-issue-item ${severity}" data-index="${idx}" data-targets='${this.safeJSON(targets)}'>
// //           <button type="button" class="aa-issue-button" aria-expanded="false">
// //             <span class="aa-left">
// //               <span class="aa-severity-dot" aria-hidden="true"></span>
// //               <span class="aa-issue-title">${title}</span>
// //             </span>
// //             <span class="aa-chevron" aria-hidden="true">›</span>
// //           </button>

// //           <div class="aa-issue-details">
// //             <p>${desc || `<span class="aa-small">No description provided.</span>`} You can learn more about this type of <a href="${issue.helpUrl}" target="_blank">issue by clicking here</a></p>

// //             <div class="aa-actions">
// //                 <button class="aa-btn-steps" data-issue-id="${issue.id}">📄 Generate resolution steps</button>
// //                 <button class="aa-btn-ai-fix" data-issue-id="${issue.id}">🤖 Fix this for me with AI</button>
// //                 <button class="aa-btn-save" data-issue-id="${issue.id}">💾 Save Changes</button>
// //             </div>
// //           </div>
// //         </li>
// //       `;

// //         //   <p class="aa-small"><strong>Nodes:</strong> ${issue.nodes?.length || 0} · Target: <code>${this.escapeHTML(firstTarget)}</code></p>
// //         //     <p><a href="${this.escapeHTML(issue.helpUrl || '#')}" target="_blank" rel="noopener">Learn more</a></p>
        
// //     });
// //     html += `</ul>`;
// //     container.innerHTML = html;

// //     // setup event delegation: click, hover
// //     // remove old handlers if exist
// //     if (this._resultsClickHandler) {
// //       this.shadowRoot.removeEventListener("click", this._resultsClickHandler);
// //       this._resultsClickHandler = null;
// //     }
// //     if (this._resultsMouseOverHandler) {
// //       this.shadowRoot.removeEventListener("mouseover", this._resultsMouseOverHandler);
// //       this._resultsMouseOverHandler = null;
// //     }
// //     if (this._resultsMouseOutHandler) {
// //       this.shadowRoot.removeEventListener("mouseout", this._resultsMouseOutHandler);
// //       this._resultsMouseOutHandler = null;
// //     }


   


// //     // click handler (delegated)
// //     this._resultsClickHandler = (e) => {
// //       const btn = e.target.closest(".aa-issue-button");
// //       if (!btn) return;
// //       const li = btn.closest(".aa-issue-item");
// //       if (!li) return;
// //       const expanded = li.classList.toggle("aa-open");
// //       btn.setAttribute("aria-expanded", expanded ? "true" : "false");

// //       // when opening, attempt to highlight/select nodes in preview
// //       if (expanded) this._onIssueOpen(li);
// //       else this._clearPreviewHighlight();
// //     };

// //     // hover handlers for preview highlight on hover
// //     this._resultsMouseOverHandler = (e) => {
// //       const item = e.target.closest(".aa-issue-item");
// //       if (!item) return;
// //       this._previewHighlightFromItem(item);
// //     };
// //     this._resultsMouseOutHandler = (e) => {
// //       const item = e.target.closest(".aa-issue-item");
// //       if (!item) return;
// //       this._clearPreviewHighlight();
// //     };

// //     this.shadowRoot.addEventListener("click", this._resultsClickHandler);
// //     this.shadowRoot.addEventListener("mouseover", this._resultsMouseOverHandler);
// //     this.shadowRoot.addEventListener("mouseout", this._resultsMouseOutHandler);
// //   }


//   /*****************************************************************
//  * Render Results (receives violations array or axe results violations)
//  *****************************************************************/
// renderResults(violations = []) {
//   console.log(violations);
//   const container = this.shadowRoot.querySelector("#aa-scan-results");
//   if (!container) return;

//   if (!Array.isArray(violations) || violations.length === 0) {
//     container.innerHTML = `<p class="aa-no-issues"><em>No issues found 🎉</em></p>`;
//     return;
//   }

//   // build list HTML
//   let html = `<ul class="aa-issues-list">`;
//   violations.forEach((issue, idx) => {
//     const impact = (issue.impact || "minor").toLowerCase();
//     const severity = ["critical","serious","moderate","minor"].includes(impact) ? impact : "minor";
//     const title = this.escapeHTML(issue.help || issue.id || "Untitled issue");
//     const desc = this.escapeHTML(issue.description || "");
//     const targets = (issue.nodes || []).map(n => n.target || []);
//     const firstTarget = targets[0]?.[0] || "";

//     html += `
//       <li class="aa-issue-item ${severity}" data-index="${idx}" data-targets='${this.safeJSON(targets)}'>
//         <button type="button" class="aa-issue-button" aria-expanded="false">
//           <span class="aa-left">
//             <span class="aa-severity-dot" aria-hidden="true"></span>
//             <span class="aa-issue-title">${title}</span>
//           </span>
//           <span class="aa-chevron" aria-hidden="true">›</span>
//         </button>

//         <div class="aa-issue-details">
//           <p>${desc || `<span class="aa-small">No description provided.</span>`} 
//              You can learn more about this type of 
//              <a href="${issue.helpUrl}" target="_blank" rel="noopener">issue by clicking here</a>
//           </p>

//           <div class="aa-actions">
//             <button class="aa-btn-steps" data-issue-id="${issue.id}">📄 Generate resolution steps</button>
//             <button class="aa-btn-ai-fix" data-issue-id="${issue.id}">🤖 Fix this for me with AI</button>
//             <button class="aa-btn-save" data-issue-id="${issue.id}">💾 Save Changes</button>
//           </div>
//         </div>
//       </li>
//     `;
//   });
//   html += `</ul>`;
//   container.innerHTML = html;

//   // remove old handlers if exist
//   if (this._resultsClickHandler) {
//     this.shadowRoot.removeEventListener("click", this._resultsClickHandler);
//     this._resultsClickHandler = null;
//   }
//   if (this._resultsMouseOverHandler) {
//     this.shadowRoot.removeEventListener("mouseover", this._resultsMouseOverHandler);
//     this._resultsMouseOverHandler = null;
//   }
//   if (this._resultsMouseOutHandler) {
//     this.shadowRoot.removeEventListener("mouseout", this._resultsMouseOutHandler);
//     this._resultsMouseOutHandler = null;
//   }

//   // click handler (delegated for toggle + actions)
//   this._resultsClickHandler = (e) => {
//     // Expand/collapse
//     const toggleBtn = e.target.closest(".aa-issue-button");
//     if (toggleBtn) {
//       const li = toggleBtn.closest(".aa-issue-item");
//       if (!li) return;
//       const expanded = li.classList.toggle("aa-open");
//       toggleBtn.setAttribute("aria-expanded", expanded ? "true" : "false");
//       if (expanded) this._onIssueOpen(li);
//       else this._clearPreviewHighlight();
//       return;
//     }

//     // Guided steps
//     const stepsBtn = e.target.closest(".aa-btn-steps");
//     if (stepsBtn) {
//       const issueId = stepsBtn.dataset.issueId;
//       this._handleGenerateSteps(issueId);
//       return;
//     }

//     // AI Auto-fix
//     const fixBtn = e.target.closest(".aa-btn-ai-fix");
//     if (fixBtn) {
//       const issueId = fixBtn.dataset.issueId;
//       this._handleAiFix(issueId);
//       return;
//     }

//     // Save fix
//     const saveBtn = e.target.closest(".aa-btn-save");
//     if (saveBtn) {
//       const issueId = saveBtn.dataset.issueId;
//       this._handleSaveFix(issueId);
//       return;
//     }
//   };

//   // hover handlers for preview highlight
//   this._resultsMouseOverHandler = (e) => {
//     const item = e.target.closest(".aa-issue-item");
//     if (!item) return;
//     this._previewHighlightFromItem(item);
//   };
//   this._resultsMouseOutHandler = (e) => {
//     const item = e.target.closest(".aa-issue-item");
//     if (!item) return;
//     this._clearPreviewHighlight();
//   };

//   // bind handlers
//   this.shadowRoot.addEventListener("click", this._resultsClickHandler);
//   this.shadowRoot.addEventListener("mouseover", this._resultsMouseOverHandler);
//   this.shadowRoot.addEventListener("mouseout", this._resultsMouseOutHandler);
// }

// /*****************************************************************
//  * Handlers for guided steps, AI fix, save fix
//  *****************************************************************/
// _handleGenerateSteps(issueId) {
//   console.log("Generate guided steps for issue:", issueId);
//   this.dispatchEvent(new CustomEvent("aa-generate-steps", {
//     detail: { issueId },
//     bubbles: true,
//     composed: true,
//   }));
// }

// _handleAiFix(issueId) {
//   console.log("AI auto-fix for issue:", issueId);
//   this.dispatchEvent(new CustomEvent("aa-ai-fix", {
//     detail: { issueId },
//     bubbles: true,
//     composed: true,
//   }));
// }

// _handleSaveFix(issueId) {
//   console.log("Save fix for issue:", issueId);
//   this.dispatchEvent(new CustomEvent("aa-save-fix", {
//     detail: { issueId },
//     bubbles: true,
//     composed: true,
//   }));
// }


  /* ---------- Guided-fix integration for web component ---------- */
    async generateGuidedFixForIndex(index, li) {
    const issue = this.savedResults?.[index];
    if (!issue) {
        this._showIssueMessage(li, 'Invalid issue selected');
        return;
    }

    const actionsEl = li.querySelector('.aa-actions');
    if (actionsEl) {
        // indicate loading
        let loader = actionsEl.querySelector('.aa-guided-loader');
        if (!loader) {
        loader = document.createElement('span');
        loader.className = 'aa-guided-loader aa-small';
        loader.textContent = 'Generating guide…';
        actionsEl.appendChild(loader);
        }
        loader.style.display = 'inline-block';
    }

    try {
        const resp = await fetch(aaEditor.ajaxurl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'aa_guided_fix',
            nonce: aaEditor.nonce || '',
            postId: aaEditor.postId || '',
            issue: JSON.stringify(issue)
        })
        });

        const json = await resp.json();

        if (!json.success) {
        this._showIssueMessage(li, 'AI Error: ' + (json.data?.message || 'Unknown'));
        return;
        }

        const data = json.data || {};
        // expected { steps: [ { title,instruction,wcag,references }, ... ] }
        this.renderGuidedSteps(li, data.steps || []);
    } catch (err) {
        console.error('Guided fix request failed', err);
        this._showIssueMessage(li, 'Request failed: ' + err.message);
    } finally {
        if (actionsEl) {
        const loader = actionsEl.querySelector('.aa-guided-loader');
        if (loader) loader.style.display = 'none';
        }
    }
    }

    renderGuidedSteps(li, steps) {
    // create container
    let container = li.querySelector('.aa-guided-steps');
    if (!container) {
        container = document.createElement('div');
        container.className = 'aa-guided-steps';
        container.style.marginTop = '12px';
        container.style.padding = '10px';
        container.style.background = '#1f2224';
        container.style.borderRadius = '8px';
        container.style.color = '#cfe2f3';
        li.querySelector('.aa-issue-details').appendChild(container);
    }
    // build HTML
    if (!Array.isArray(steps) || steps.length === 0) {
        container.innerHTML = '<p class="aa-small">No guided steps returned.</p>';
        return;
    }
    let html = '<ol>';
    steps.forEach(s => {
        const title = s.title ? `<strong>${this.escapeHTML(s.title)}</strong><br/>` : '';
        const instr = s.instruction ? this.escapeHTML(s.instruction) : '';
        const wcag = Array.isArray(s.wcag) && s.wcag.length ? `<div class="aa-small">WCAG: ${s.wcag.join(', ')}</div>` : '';
        const refs = Array.isArray(s.references) && s.references.length
        ? `<div class="aa-small">References: ${s.references.map(r => `<a href="${this.escapeHTML(r)}" target="_blank" rel="noopener">${this.escapeHTML(r)}</a>`).join(', ')}</div>`
        : '';
        html += `<li style="margin-bottom:10px;">${title}<div>${instr}</div>${wcag}${refs}</li>`;
    });
    html += '</ol>';
    container.innerHTML = html;
    }

    /* helper to show temporary messages inside issue details */
    _showIssueMessage(li, text) {
    let box = li.querySelector('.aa-issue-msg');
    if (!box) {
        box = document.createElement('div');
        box.className = 'aa-issue-msg aa-small';
        box.style.marginTop = '8px';
        li.querySelector('.aa-issue-details').appendChild(box);
    }
    box.textContent = text;
    setTimeout(() => { if (box) box.textContent = ''; }, 8000);
    }


  /*****************************************************************
   * When an issue item is opened: highlight/scroll/select elements
   *****************************************************************/
  async _onIssueOpen(li) {
    // parse dataset targets (array of arrays)
    const raw = li.dataset.targets || "[]";
    let selectors = [];
    try { selectors = JSON.parse(raw); } catch(e) { selectors = []; }

    // flatten and remove empty
    selectors = Array.isArray(selectors) ? selectors.flat().filter(Boolean) : [];

    // locate preview iframe & doc
    const previewIframe = document.querySelector("#bricks-builder-iframe") || document.querySelector("iframe");
    if (!previewIframe) return;
    let previewDoc;
    try { previewDoc = previewIframe.contentDocument || previewIframe.contentWindow.document; } catch(e) { previewDoc = null; }
    if (!previewDoc) return;

    // highlight first match; also try to send selection to Bricks if brx-id found
    for (const sel of selectors) {
      try {
        const el = previewDoc.querySelector(sel);
        if (!el) continue;

        // scroll into view
        try { el.scrollIntoView({ behavior: "smooth", block: "center" }); } catch(e){}

        // visually highlight
        this._applyPreviewOutline(el);

        // if Bricks-specific id exists, postMessage to parent to select
        // Bricks often keeps data-brx-id on elements — adapt if Bricks uses a different attribute
        const brxId = el.dataset?.brxId || el.getAttribute("data-brx-id") || el.id;
        if (brxId) {
          // Post message to parent (Bricks likely listens). Use '*' for origin - adapt if you want origin restriction.
          previewIframe.contentWindow.parent.postMessage({ type: "bricks.selectElement", id: brxId }, "*");
        }

        // we only highlight the first matching node
        break;
      } catch (err) {
        console.warn("Error matching selector in preview:", sel, err);
      }
    }
  }

  _applyPreviewOutline(el) {
    // remove previous
    this._clearPreviewHighlight();

    // add outline style via inline style (so it is visible even if preview has own rules)
    this._previewHighlight = el;
    // store original style to restore
    this._prevOutline = el.style.outline;
    this._prevZ = el.style.zIndex;
    el.style.outline = "3px solid rgba(255,59,48,0.92)";
    el.style.zIndex = "999999";
    // remove after 3.5s
    this._previewHighlightTimeout = setTimeout(() => this._clearPreviewHighlight(), 3500);
  }

  _clearPreviewHighlight() {
    if (!this._previewHighlight) return;
    try {
      this._previewHighlight.style.outline = this._prevOutline || "";
      this._previewHighlight.style.zIndex = this._prevZ || "";
    } catch (e) { /* ignore */ }
    this._previewHighlight = null;
    clearTimeout(this._previewHighlightTimeout);
  }

  _previewHighlightFromItem(item) {
    // similar to _onIssueOpen but only highlight on hover, non-destructive
    const raw = item.dataset.targets || "[]";
    let selectors = [];
    try { selectors = JSON.parse(raw); } catch(e) { selectors = []; }
    selectors = Array.isArray(selectors) ? selectors.flat().filter(Boolean) : [];

    const previewIframe = document.querySelector("#bricks-builder-iframe") || document.querySelector("iframe");
    if (!previewIframe) return;
    let previewDoc;
    try { previewDoc = previewIframe.contentDocument || previewIframe.contentWindow.document; } catch(e) { previewDoc = null; }
    if (!previewDoc) return;

    for (const sel of selectors.slice(0,2)) { // highlight first one or two matches
      try {
        const el = previewDoc.querySelector(sel);
        if (!el) continue;
        // quick highlight (temporary)
        const prev = el.style.outline;
        el.style.outline = "2px dashed rgba(90,200,250,0.9)";
        setTimeout(() => { try{ el.style.outline = prev || ""; } catch(e){} }, 800);
        break;
      } catch (err) {
        // ignore invalid selector
      }
    }
  }

  /*****************************************************************
   * runScan: inject axe into preview iframe if needed, run scan,
   * then render results and optionally post to server
   *****************************************************************/
  async runScan(auto = false) {
    const resultsEl = this.shadowRoot.querySelector("#aa-scan-results");
    if (!resultsEl) return;
    resultsEl.innerHTML = `<p class="aa-no-issues"><em>Scanning with axe-core…</em></p>`;

    try {
      const previewIframe = document.querySelector("#bricks-builder-iframe") || document.querySelector("iframe");
      if (!previewIframe) {
        resultsEl.innerHTML = `<p class="aa-no-issues"><em>Preview iframe not found. Ensure you're in Bricks editor.</em></p>`;
        return;
      }

      const previewWin = previewIframe.contentWindow;
      let previewDoc;
      try { previewDoc = previewIframe.contentDocument || previewWin.document; } catch(e){ previewDoc = null; }
      if (!previewDoc) {
        resultsEl.innerHTML = `<p class="aa-no-issues"><em>Preview document not accessible (cross-origin?).</em></p>`;
        return;
      }

      // helper to run axe inside preview window
      const runAxeInPreview = async () => {
        // runOnly filter to cover WCAG criteria
        const opts = { runOnly: ["wcag2a", "wcag2aa", "wcag21aa"] };
        // axe.run returns { violations, incomplete, passes, etc. }
        return await previewWin.axe.run(previewDoc, opts);
      };

      let results;
      // if axe not present in preview, inject it and wait for load
      if (!previewWin.axe) {
        await new Promise((resolve, reject) => {
          const script = previewDoc.createElement("script");
          script.src = "https://cdnjs.cloudflare.com/ajax/libs/axe-core/4.10.0/axe.min.js";
          script.onload = async () => {
            try {
              // small delay to ensure axe is fully available
              results = await runAxeInPreview();
              resolve(results);
            } catch (err) { reject(err); }
          };
          script.onerror = (e) => reject(new Error("Failed to load axe-core into preview iframe"));
          (previewDoc.head || previewDoc.documentElement).appendChild(script);
        });
      } else {
        // axe already available
        results = await runAxeInPreview();
      }

      console.log("axe results", results);
      console.log("violations:", results.violations);


      // Render violations (prefer results.violations)
      this.renderResults(results.violations || []);

      // optionally persist results via ajax to server (if aaEditor ajax present)
      if (typeof aaEditor !== "undefined" && aaEditor.ajaxurl) {
        try {
          await fetch(aaEditor.ajaxurl, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: new URLSearchParams({
              action: "run_scan",
              nonce: aaEditor.nonce || "",
              postId: aaEditor.postId || "",
              results: JSON.stringify(results),
            }),
          });
        } catch (err) {
          console.warn("Failed to post scan results to server:", err);
        }
      }

    } catch (err) {
      console.error(err);
      const resultsElInner = this.shadowRoot.querySelector("#aa-scan-results");
      if (resultsElInner) resultsElInner.innerHTML = `<p class="aa-no-issues"><strong>Error:</strong> ${this.escapeHTML(err.message || String(err))}</p>`;
    }
  }
}

// register component
customElements.define('aa-dashboard', AADashboard);

// auto-insert into top window
document.addEventListener('DOMContentLoaded', () => {
  if (window.self === window.top && !document.querySelector('aa-dashboard')) {
    document.body.appendChild(document.createElement('aa-dashboard'));
  }
});





