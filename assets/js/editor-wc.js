/* Web Component UI for Accessibility Auditor (editor-wc.js)
   - Injects axe-core into preview iframe (if same-origin)
   - Renders results, highlights preview elements and posts selection to Bricks
*/

class AADashboard extends HTMLElement {
  constructor() {
    super();
    this._stepsCache = {}; // 🔹 cache for guided steps keyed by issueId
    this.attachShadow({ mode: "open" });

    // memory for saved results (violations array)
    this.savedResults = [];

    // default score = 0 (F)
    this.score = 0;

     
    if (window.aaEditor?.results) {

        // this.savedResults = aaEditor.results?.violations || [];

        // console.log(this.savedResults);

      try {
        const parsed = typeof aaEditor.results === "string" ? JSON.parse(aaEditor.results) : aaEditor.results;
        this.savedResults = parsed?.violations || [];
      } catch (e) {
        console.warn("Failed to parse saved results:", e);
      }

        this.score = parseInt(window.aaEditor.score || 0, 10);

    }

    // store currently highlighted preview element for cleanup
    this._previewHighlight = null;


    // render initial floating button
    this.renderButton();
  }



 updateAccessibilityUI(score) {


  const grade = this._calculateGrade(score);
  const color = this._gradeColor(grade);
  

  console.log(grade);
  console.log(color);

  // 3. update floating button dot
  const dot = document.getElementById("aa-status-dot");
  if (dot) {
    dot.style.background = color;
  }

  // 4. update panel badge
  const badge = document.querySelector(".aa-score-badge");
  if (badge) {
    badge.textContent = grade;
    badge.style.color = color;
    badge.style.border = `1px solid ${color}`;
    
  }
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
     
     const badgeColor = this._gradeColor(this._calculateGrade(this.score));

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
          transition: background 0.3s ease;
        }

      </style>

      <button id="aa-accessibility-btn" title="Accessibility Auditor" aria-label="Open Accessibility Auditor">
        <svg width="15" height="20" viewBox="0 0 15 20" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M5.22307 1.925C5.22307 0.862813 6.08588 0 7.14807 0C8.21026 0 9.07307 0.862813 9.07307 1.925C9.07307 2.98719 8.21026 3.85 7.14807 3.85C6.08588 3.85 5.22307 2.98719 5.22307 1.925ZM0.173384 3.25531C0.499946 2.74312 1.18057 2.59531 1.69276 2.92188L4.50807 4.72656C5.29526 5.23187 6.21307 5.5 7.14807 5.5C8.08307 5.5 9.00088 5.23187 9.78807 4.72656L12.6034 2.92188C13.1156 2.59531 13.7962 2.74312 14.1228 3.25531C14.4493 3.7675 14.3015 4.44812 13.7893 4.77469L10.974 6.57937C10.6303 6.79938 10.2693 6.98844 9.89463 7.14656V10.9381C9.89463 11.5775 10.0081 12.2169 10.2246 12.8184L10.3896 13.2722V13.2756L12.0259 17.7753C12.2081 18.2738 12.0053 18.8203 11.5687 19.0884C11.5068 19.1262 11.4381 19.1606 11.3693 19.1847C10.8709 19.3669 10.3243 19.1641 10.0562 18.7275C10.0184 18.6656 9.98401 18.5969 9.95995 18.5281L8.3237 14.0284V14.025C8.14151 13.53 7.67057 13.2 7.14463 13.2C6.61526 13.2 6.14432 13.53 5.96557 14.025L4.33276 18.5247C4.13682 19.0609 3.56963 19.3531 3.02995 19.2156C2.99557 19.2053 2.95776 19.195 2.92338 19.1812C2.35276 18.975 2.05713 18.3425 2.26682 17.7719L3.90307 13.2722L4.06807 12.815C4.28807 12.2134 4.39807 11.5775 4.39807 10.9347V7.14313C4.02338 6.985 3.66588 6.79594 3.32213 6.57594L0.506821 4.77469C-0.00536631 4.44812 -0.153179 3.7675 0.173384 3.25531Z" fill="white"/>
        </svg>
       <span class="aa-status-dot" id="aa-status-dot" style="background:${badgeColor};"></span>
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

    const grade = this._calculateGrade(this.score);
    const badgeColor = this._gradeColor(grade);

    this.shadowRoot.innerHTML = `
      <style>
        :host { all: initial; }
        @import url('https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap');
       .aa-panel {
          width: 400px;
          background: #16191B;
          color: #fff;
          font-family: "Inter", sans-serif;
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

        .aa-panel-header { display: flex; align-items: center; justify-content: space-between; padding: 18px 24px 12px 24px; border-bottom: 1px solid #363E48; }
        .aa-panel-title { font-weight: 700; font-size: 15px; color: #DEE2E6; }
        .aa-panel-score { display: flex; align-items: center; gap: 8px; }
        .aa-score-label { font-size: 12px; color: #DCE0E4; margin-right: 4px; font-weight: 500; }
        .aa-score-badge { color: #fff; border-radius: 100px;font-weight: 600; font-size: 11px;padding: 2px 10px;margin-right: 8px;display: inline-block;}

        .aa-close-btn { background: #252A31; border: none; border-radius: 5px; cursor: pointer; }
        .aa-panel-body { overflow-y: auto; height: calc(100vh - 88px); }
        .aa-pane-content { overflow:auto; flex:1; }

        .aa-accordion-list { margin: 0; padding: 0 30px; list-style: none; }
        .aa-accordion-item { background: #252A31; border-radius: 5px; margin-top: 18px; box-shadow: 0 1px 2px #0002; position: relative; }
        .aa-accordion-btn{display:flex;align-items:center;justify-content:space-between;width:100%;background:none;border:none;color:#DCE0E6;font-weight:700;font-size:12px;padding:18px 16px 18px 20px;border-radius:5px;cursor:pointer;text-align:left;position:relative}
        .aa-arrow{display:inline-flex;transition:transform .2s;margin-left:auto}
        .aa-accordion-item.aa-expanded .aa-arrow{transform:rotate(90deg);background:#45515F;border-radius:3px;padding:2px}
        .aa-accordion-item.aa-expanded .aa-accordion-btn{color:#FDD042;background:#363E48}
        .aa-dot { position: absolute; left: -18px; top: 50%; transform: translateY(-50%); width: 5px; height: 5px; border-radius: 50%; }
        .aa-dot.aa-red { background: #ff3b3b; }
        .aa-dot.aa-orange { background: #ffb300; }
        .aa-dot.aa-critical { background:#E74C3C; } /* red */
        .aa-dot.aa-serious  { background:#E67E22; } /* orange */
        .aa-dot.aa-moderate { background:#F1C40F; } /* yellow */
        .aa-dot.aa-minor    { background:#2ECC71; } /* green (optional) */
        .aa-accordion-content { display: none; background: #16191B; border-radius: 0 0 8px 8px; padding: 15px 10px; font-size: 11px; color: #7D8B9B; font-weight: 500; }
        .aa-accordion-item.aa-expanded .aa-accordion-content { display: block; animation: aa-fadeIn 0.2s; }
        @keyframes aa-fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .aa-accordion-actions { display:flex; gap:12px; margin:18px 0 0 0; flex-direction:column; }
        .aa-action-btn { background:#16191B; color:#DEE2E6; border:none; border-radius:6px; padding:0; font-size:12px; font-weight:400; cursor:pointer; display:flex; align-items:center; gap:6px; transition:background 0.2s, border 0.2s; width:100%; box-sizing:border-box; justify-content:space-between; }
        .aa-action-btn span { background:#252A31; width:23px; height:23px; line-height:23px; border-radius:2px; }
        .aa-action-btn.aa-selected span, .aa-action-btn:active span { background:#FDD042; color:#16191B; }
        .aa-save-changes { background:#363E48; color:#DEE2E6; border:none; border-radius:5px; padding:9px 0; font-size:9px; font-weight:700; cursor:pointer; margin-bottom:6px; transition:background 0.2s; line-height:130%; width:100%; display:block; margin-top:50px; }
        .aa-resolution-steps { border-radius:8px; margin-top:18px; padding:0; color:#fff; font-size:13px; border:none; box-shadow:0 1px 2px #0002; }
        .aa-resolution-steps-title { font-weight:700; font-size:12px; background:#252A31; border-radius:5px 5px 0 0; padding:14px 18px 10px 18px; border-bottom:1px solid #16191B; color:#fff; line-height:130%; }
        .aa-resolution-steps-list { padding:12px 18px 18px 28px; color:#DEE2E6; font-weight:400; line-height:130%; background:#252A31; border-radius:0 0 5px 5px; }
        .aa-resolution-steps-list ol li ul { padding:0; }
        .aa-resolution-steps-list ol li ul li ul { padding-left:20px; }
        .aa-resolution-actions { margin-top:25px; }
        .aa-save-reload-btn { background:#363E48; color:#DEE2E6; border:none; border-radius:5px; padding:9px 0; font-size:9px; font-weight:700; cursor:pointer; margin-bottom:6px; transition:background 0.2s; line-height:130%; width:100%; }
        .aa-ai-success { border-radius:8px; margin-top:18px; color:#fff; font-size:12px; border:none; box-shadow:0 1px 2px #0002; text-align:left; }
        .aa-ai-success strong { background:#232527; padding:18px; color:#FFFFFF; font-weight:700; display:block; border-bottom:solid 1px #16191B; border-radius:5px 5px 0 0; }
        .aa-ai-success span { padding:18px; font-size:13px; font-weight:400; line-height:130%; color:#DEE2E6; display:block; background:#232527; border-radius:0 0 5px 5px; }
        .aa-ai-actions { display:flex; flex-direction:column; gap:10px; margin:18px 0 0 0; }
        .aa-accept-btn { background:#FDD042; color:#181A1B; border:none; border-radius:6px; padding:9px 0; font-size:9px; font-weight:700; cursor:pointer; margin-bottom:6px; transition:background 0.2s; line-height:130%; }
        .aa-accept-btn:hover { background:#ffb300; }
        .aa-reject-btn { background:none; color:#8A9199; border:1px solid #8A9199; border-radius:6px; padding:9px 0; font-size:9px; font-weight:700; cursor:pointer; transition:background 0.2s, color 0.2s; line-height:130%; }
        .aa-reject-btn:hover { background:#232527; color:#ff3b3b; }
        a { color:#7D8B9B; text-decoration:underline; }
        @media (max-width:400px) { body, .aa-panel { max-width:100vw; } }


       
        
       
      </style>

      <div class="aa-panel" role="dialog" aria-label="Accessibility Auditor">
        <div class="aa-panel-header">
			<span class="aa-panel-title">ACCESSIBILITY</span>
			<div class="aa-panel-score">
				<span class="aa-score-label">Page score:</span>
				<span class="aa-score-badge" style="color:${badgeColor}; border:1px solid ${badgeColor}">${grade}</span>
				<button class="aa-close-btn" id="aa-close-btn" title="Close">
                  <svg width="9" height="9" viewBox="0 0 9 9" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M1.28094 0.221983C0.988091 -0.070871 0.512495 -0.070871 0.219641 0.221983C-0.0732136 0.514838 -0.0732136 0.990433 0.219641 1.28329L3.4387 4.5L0.221983 7.71905C-0.070871 8.01191 -0.070871 8.4875 0.221983 8.78036C0.514838 9.07321 0.990433 9.07321 1.28329 8.78036L4.5 5.5613L7.71905 8.77802C8.01191 9.07087 8.4875 9.07087 8.78036 8.77802C9.07321 8.48516 9.07321 8.00957 8.78036 7.71671L5.5613 4.5L8.77802 1.28094C9.07087 0.988091 9.07087 0.512495 8.77802 0.219641C8.48516 -0.0732136 8.00957 -0.0732136 7.71671 0.219641L4.5 3.4387L1.28094 0.221983Z" fill="#DCE0E4"/>
                  </svg>
                </button>
			</div>
		</div>
         <div class="aa-panel-body">
          
          <div class="aa-pane-content">
            <div id="aa-scan-results" class="aa-results">
              <p class="aa-no-issues"><em>No scan run yet.</em> <button id="aa-run-scan" class="aa-btn">🔍 Run Accessibility Scan</button></p>
            </div>
          </div>
        </div>
     </div>
    `;

    // events
    this.shadowRoot.querySelector("#aa-close-btn").addEventListener("click", () => this.renderButton());
    this.shadowRoot.querySelector("#aa-run-scan").addEventListener("click", () => this.runScan());

    // preload saved results if present

    

    if (window.aaEditor?.needsScan == 1) {

      console.log("needs scan is true");
       // optionally auto-run
       //this.runScan();
       this.runScan().then(() => {
          // after scan completes, reset flag
          window.aaEditor.needsScan = 0;
          console.log("scan complete, flag reset to 0");
        });
    } else if (this.savedResults && this.savedResults.length) {

      console.log("needs scan is false");
      this.renderResults(this.savedResults);
     } 

  }




/*****************************************************************
 * Render Results (receives violations array or axe results violations)
 *****************************************************************/
renderResults(violations = []) {
  console.log(violations);
  this._violations = violations; // store for highlight lookup
  const container = this.shadowRoot.querySelector("#aa-scan-results");
  if (!container) return;

  if (!Array.isArray(violations) || violations.length === 0) {
    container.innerHTML = `<p class="aa-no-issues"><em>No issues found 🎉</em></p>`;
    return;
  }

  // build accordion HTML
  let html = `<ul class="aa-accordion-list">`;
  violations.forEach(issue => {
    const impact = (issue.impact || "minor").toLowerCase();
    const validImpacts = ["critical","serious","moderate","minor"];
    const severity = validImpacts.includes(impact) ? impact : "minor";
    const title = this.escapeHTML(issue.help || issue.id || "Untitled issue");
    const desc = this.escapeHTML(issue.description || "");
    const targets = (issue.nodes || []).map(n => n.target || []);


    const nodes = issue.nodes || [];

       // Only render "Affected Elements" list if more than one node
    const nodesHTML = nodes.length > 1 ? `
      <div class="aa-nodes-list">
        <div class="aa-nodes-title">Affected Elements:</div>
        <ul>
          ${nodes.map((n, idx) => `
            <li >
              <a 
                href="#"
                class="aa-node-link" 
                data-issue-id="${issue.id}" 
                data-node-index="${idx}">
                ${this.escapeHTML(n.target.join(" , ")) || "(unknown selector)"}
              </a>
            </li>`).join("")}
        </ul>
      </div>
    ` : "";

    
    html += `
      <li class="aa-accordion-item ${severity}" 
          data-id="${issue.id}" 
          data-targets='${this.safeJSON(targets)}'>
        <button class="aa-accordion-btn" tabindex="0" aria-expanded="false">
          <span class="aa-dot aa-${impact}"></span>
          ${title}
          <span class="aa-arrow" aria-hidden="true">
            <svg width="7" height="13" viewBox="0 0 7 13" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M6.80101 6.006C7.06633 6.27885 7.06633 6.72006 6.80101 6.99001L1.15585 12.7954C0.890524 13.0682 0.461492 13.0682 0.198992 12.7954C-0.0635081 12.5225 -0.0663306 12.0813 0.198992 11.8114L5.36431 6.49946L0.198992 1.18756C-0.0663306 0.914707 -0.0663306 0.4735 0.198992 0.203552C0.464314 -0.0663973 0.893347 -0.0692999 1.15585 0.203552L6.80101 6.0089V6.006Z" fill="#DCE0E4"/>
            </svg>
          </span>
        </button>

        <div class="aa-accordion-content">
          <p>${desc || `<span class="aa-small">No description provided.</span>`} 
             <a href="${issue.helpUrl}" target="_blank">Learn more</a>
             
           ${nodesHTML}    
          </p>

         

          <div class="aa-accordion-actions">
            <button class="aa-action-btn aa-btn-steps" data-issue-id="${issue.id}">
              Generate resolution steps <span><svg width="8" height="10" viewBox="0 0 8 10" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                   <path d="M0.666667 0.892857C0.666667 0.794643 0.741667 0.714286 0.833333 0.714286H3.66667V9.28571H2C1.81667 9.28571 1.66667 9.44643 1.66667 9.64286C1.66667 9.83929 1.81667 10 2 10H6C6.18333 10 6.33333 9.83929 6.33333 9.64286C6.33333 9.44643 6.18333 9.28571 6 9.28571H4.33333V0.714286H7.16667C7.25833 0.714286 7.33333 0.794643 7.33333 0.892857V2.5C7.33333 2.69643 7.48333 2.85714 7.66667 2.85714C7.85 2.85714 8 2.69643 8 2.5V0.892857C8 0.399554 7.62708 0 7.16667 0H0.833333C0.372917 0 0 0.399554 0 0.892857V2.5C0 2.69643 0.15 2.85714 0.333333 2.85714C0.516667 2.85714 0.666667 2.69643 0.666667 2.5V0.892857Z" fill="#DEE2E6"/>
                                                </svg>
                                          </span>
            </button>
            <!--<button class="aa-action-btn aa-btn-ai-fix" data-issue-id="${issue.id}">
              Fix with AI <span><svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                 <path d="M8.25002 0.374981V1.8749H9.75001C9.95626 1.8749 10.125 2.04364 10.125 2.24988C10.125 2.45612 9.95626 2.62486 9.75001 2.62486H8.25002V4.12479C8.25002 4.33103 8.08127 4.49977 7.87502 4.49977C7.66877 4.49977 7.50002 4.33103 7.50002 4.12479V2.62486H6.00003C5.79378 2.62486 5.62503 2.45612 5.62503 2.24988C5.62503 2.04364 5.79378 1.8749 6.00003 1.8749H7.50002V0.374981C7.50002 0.168741 7.66877 0 7.87502 0C8.08127 0 8.25002 0.168741 8.25002 0.374981ZM3.58129 6.22233C3.47113 6.44498 3.2602 6.59731 3.01645 6.63247L0.965678 6.93011L2.45161 8.38081C2.62739 8.5519 2.70942 8.80032 2.66723 9.04406L2.31567 11.09L4.14848 10.1245C4.36644 10.0096 4.62894 10.0096 4.84691 10.1245L6.67971 11.09L6.32815 9.04406C6.28596 8.80032 6.368 8.55424 6.54378 8.38081L8.02971 6.93011L5.97894 6.63247C5.73519 6.59731 5.52425 6.44263 5.41409 6.22233L4.49769 4.36149L3.58129 6.22233ZM3.99379 3.68887C4.20004 3.26936 4.79769 3.26936 5.00394 3.68887L6.08909 5.89188L8.51486 6.24577C8.97658 6.31373 9.15939 6.87855 8.82658 7.20431L7.07112 8.91985L7.48596 11.3408C7.56565 11.8002 7.08284 12.1517 6.67034 11.9338L4.50004 10.7901L2.32973 11.9338C1.91724 12.1517 1.43443 11.8002 1.51411 11.3408L1.92661 8.91985L0.17115 7.20431C-0.164004 6.87855 0.0211509 6.31139 0.482867 6.24577L2.90864 5.89188L3.99379 3.68887ZM10.5 3.7498C10.7063 3.7498 10.875 3.91855 10.875 4.12479V4.87475H11.625C11.8313 4.87475 12 5.04349 12 5.24973C12 5.45597 11.8313 5.62471 11.625 5.62471H10.875V6.37467C10.875 6.58091 10.7063 6.74965 10.5 6.74965C10.2938 6.74965 10.125 6.58091 10.125 6.37467V5.62471H9.37501C9.16876 5.62471 9.00001 5.45597 9.00001 5.24973C9.00001 5.04349 9.16876 4.87475 9.37501 4.87475H10.125V4.12479C10.125 3.91855 10.2938 3.7498 10.5 3.7498Z" fill="#DEE2E6"/>
                                 </svg>
                           </span>
            </button>-->
          </div>

          <!-- resolution steps section -->
          <div id="resolution-steps-${issue.id}" class="aa-resolution-steps" style="display:none;">
            <div class="aa-resolution-steps-title">RESOLUTION STEPS</div>
            <div class="aa-resolution-steps-list"></div>
            <div class="aa-resolution-actions">
                <button class="aa-save-reload-btn" data-issue-id="${issue.id}">SAVE & RELOAD</button>
            </div>
          </div>

          <!-- ai success section -->
          <div id="ai-success-${issue.id}" class="aa-ai-success" style="display:none;">
            <div class="aa-ai-text"></div>
            <div class="aa-ai-actions">
                 <button class="aa-accept-btn" data-issue-id="${issue.id}">ACCEPT CHANGES</button>
                 <button class="aa-reject-btn" data-issue-id="${issue.id}">REJECT CHANGES AND REVERT</button>
             </div>
          </div>
        </div>
      </li>
    `;
  });
  html += `</ul>`;
  container.innerHTML = html;

  /*******************
   * Remove old handlers
   *******************/
  if (this._resultsClickHandler) this.shadowRoot.removeEventListener("click", this._resultsClickHandler);
  if (this._resultsMouseOverHandler) this.shadowRoot.removeEventListener("mouseover", this._resultsMouseOverHandler);
  if (this._resultsMouseOutHandler) this.shadowRoot.removeEventListener("mouseout", this._resultsMouseOutHandler);
  


  /*******************
 * Event Handlers
 *******************/
this._resultsClickHandler = async (e) => {
  const btn       = e.target.closest(".aa-accordion-btn");
  const stepsBtn  = e.target.closest(".aa-btn-steps");
  const aiBtn     = e.target.closest(".aa-btn-ai-fix");
  const nodeLink  = e.target.closest(".aa-node-link");

  const saveBtn   = e.target.closest(".aa-save-reload-btn");
  const acceptBtn = e.target.closest(".aa-accept-btn");
  const rejectBtn = e.target.closest(".aa-reject-btn");

  // Accordion open/close
  if (btn) {
    const item = btn.closest(".aa-accordion-item");
    if (!item) return;

    // close all others
    this.shadowRoot.querySelectorAll(".aa-accordion-item").forEach(i => {
      if (i !== item) {
        i.classList.remove("aa-expanded");
        i.querySelector(".aa-accordion-btn")?.setAttribute("aria-expanded", "false");
      }
    });

    const expanded = item.classList.toggle("aa-expanded");
    btn.setAttribute("aria-expanded", expanded ? "true" : "false");
    if (expanded) this._onIssueOpen(item);
    else this._clearPreviewHighlight();
    return;
  }

  // Generate resolution steps
  if (stepsBtn) {
    const issueId = stepsBtn.dataset.issueId;
    const stepsPanel = this.shadowRoot.querySelector(`#resolution-steps-${issueId}`);
    const aiPanel    = this.shadowRoot.querySelector(`#ai-success-${issueId}`);

    // 🔹 Toggle selected class
    stepsBtn.classList.add("aa-selected");
    const aiButton = this.shadowRoot.querySelector(`.aa-btn-ai-fix[data-issue-id="${issueId}"]`);
    aiButton?.classList.remove("aa-selected");

    aiPanel.style.display = "none";
    stepsPanel.style.display = "block";

    // 🔹 Check cache first
    if (this._stepsCache[issueId]) {
      stepsPanel.querySelector(".aa-resolution-steps-list").innerHTML =
        this._stepsCache[issueId];
      return;
    }
    stepsPanel.querySelector(".aa-resolution-steps-list").innerHTML =
      `<p class="aa-loading">Generating guided steps…</p>`;

    const issue = this._violations.find(v => v.id === issueId);
    const steps = await this._generateGuidedFix(issue);
    this._stepsCache[issueId] = steps;

    stepsPanel.querySelector(".aa-resolution-steps-list").innerHTML = steps;
    return;
  }

  // Apply AI fix
  if (aiBtn) {
    const issueId = aiBtn.dataset.issueId;
    const aiPanel = this.shadowRoot.querySelector(`#ai-success-${issueId}`);
    const stepsPanel = this.shadowRoot.querySelector(`#resolution-steps-${issueId}`);



    // 🔹 Toggle selected class
    aiBtn.classList.add("aa-selected");
    const stepsButton = this.shadowRoot.querySelector(`.aa-btn-steps[data-issue-id="${issueId}"]`);
    stepsButton?.classList.remove("aa-selected");

    stepsPanel.style.display = "none";
    aiPanel.style.display = "block";
    const aiTextDiv = aiPanel?.querySelector('.aa-ai-text');

    // 🔹 Get the issue data
    const issue = this._violations.find(v => v.id === issueId);
    if (!issue) return;


    aiTextDiv.innerHTML = `
    <div class="aa-loading" style="padding:10px;">
      <strong>Fixing with AI…</strong><br>
      <small>This may take a few seconds.</small>
    </div>
  `;

    // 🔹 Show loading state
    // aiPanel.innerHTML = `
    //   <div class="aa-loading" style="padding:10px;">
    //     <strong>Fixing with AI…</strong><br>
    //     <small>This may take a few seconds.</small>
    //   </div>
    // `;

    // 🔹 Update button text temporarily
    const originalText = aiBtn.textContent;
    aiBtn.textContent = "Fixing with AI…";
    aiBtn.disabled = true;

    try {
      // 🔹 Call API
      const result = await this._applyAutoFix(issue);

      if (result?.error) {
        aiTextDiv.innerHTML = `<p class="aa-error">AI Fix failed: ${result.error}</p>`;
      } else if (result?.changelog?.length) {
        // 🔹 Show changelog summary
        const logHtml = result.changelog
          .map(item => `<li>${this.escapeHTML(item)}</li>`)
          .join("");
        aiTextDiv.innerHTML = `
           <strong>AI CHANGE MADE SUCCESSFULLY</strong>
           <span>Validate changes and confirm or reject.</span>
        `;
      } else {
        aiTextDiv.innerHTML = `<p>No automatic changes were necessary or detected.</p>`;
      }
    } catch (err) {
      aiTextDiv.innerHTML = `<p class="aa-error">Error running AI Fix: ${err.message}</p>`;
      console.error("AI Fix error:", err);
    } finally {
      // 🔹 Reset button
      aiBtn.textContent = originalText;
      aiBtn.disabled = false;
    }

    return;
    }

    // Save & Reload
    if (saveBtn) {
      const issueId = saveBtn.dataset.issueId;
      const issue = this._violations.find(v => v.id === issueId);
      await this._saveFix(issue);
      location.reload();
      return;
    }

    // Accept AI fix
    if (acceptBtn) {
      const issueId = acceptBtn.dataset.issueId;
      const issue = this._violations.find(v => v.id === issueId);
      await this._saveFix(issue);
      location.reload();
      return;
    }

    // Reject AI fix — revert Bricks content to pre-fix snapshot
    if (rejectBtn) {
      if (this._lastRevisionKey) {
        await fetch(`${aaEditor.root}revert-fix`, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-WP-Nonce": aaEditor.restNonce,
          },
          body: JSON.stringify({ post_id: aaEditor.postId, revision_key: this._lastRevisionKey })
        });
        location.reload();
      } else {
        const issueId = rejectBtn.dataset.issueId;
        const aiPanel = this.shadowRoot.querySelector(`#ai-success-${issueId}`);
        if (aiPanel) aiPanel.style.display = "none";
      }
      return;
    }

    // 🔹 Node link clicked (affected elements)
    if (nodeLink) {
      e.preventDefault(); // prevent scrolling to top
      const issueId = nodeLink.dataset.issueId;
      const nodeIndex = parseInt(nodeLink.dataset.nodeIndex, 10);
      const issue = this._violations.find(v => v.id === issueId);
      const node = issue?.nodes?.[nodeIndex];
      if (node) {
        this._highlightNode(node);

        // Mark this node as active
        this.shadowRoot
          .querySelectorAll(`.aa-node-link[data-issue-id="${issueId}"]`)
          .forEach(l => l.classList.remove("aa-node-selected"));
        nodeLink.classList.add("aa-node-selected");
      }
      return;
  }
};


  // /*******************
  //  * Event Handlers
  //  *******************/
  // this._resultsClickHandler = async (e) => {
  //   const btn       = e.target.closest(".aa-accordion-btn");
  //   const stepsBtn  = e.target.closest(".aa-btn-steps");
  //   const aiBtn     = e.target.closest(".aa-btn-ai-fix");
  //   const saveBtn   = e.target.closest(".aa-save-reload-btn");
  //   const acceptBtn = e.target.closest(".aa-accept-btn");
  //   const rejectBtn = e.target.closest(".aa-reject-btn");

  //   // Accordion open/close
  //   if (btn) {
  //     const item = btn.closest(".aa-accordion-item");
  //     if (!item) return;

  //     // close all others
  //     this.shadowRoot.querySelectorAll(".aa-accordion-item").forEach(i => {
  //       if (i !== item) {
  //         i.classList.remove("aa-expanded");
  //         i.querySelector(".aa-accordion-btn")?.setAttribute("aria-expanded", "false");
  //       }
  //     });

  //     const expanded = item.classList.toggle("aa-expanded");
  //     btn.setAttribute("aria-expanded", expanded ? "true" : "false");
  //     if (expanded) this._onIssueOpen(item);
  //     else this._clearPreviewHighlight();
  //     return;
  //   }

  //   // Generate resolution steps
  //   if (stepsBtn) {
  //     const issueId = stepsBtn.dataset.issueId;
  //     const stepsPanel = this.shadowRoot.querySelector(`#resolution-steps-${issueId}`);
  //     const aiPanel    = this.shadowRoot.querySelector(`#ai-success-${issueId}`);

  //     // 🔹 Toggle selected class
  //     stepsBtn.classList.add("aa-selected");
  //     const aiButton = this.shadowRoot.querySelector(`.aa-btn-ai-fix[data-issue-id="${issueId}"]`);
  //     aiButton?.classList.remove("aa-selected");

  //     aiPanel.style.display = "none";
  //     stepsPanel.style.display = "block";


  //     // 🔹 Check cache first
  //     if (this._stepsCache[issueId]) {
  //           stepsPanel.querySelector(".aa-resolution-steps-list").innerHTML =
  //           this._stepsCache[issueId];
  //           return;
  //       }
  //     stepsPanel.querySelector(".aa-resolution-steps-list").innerHTML =
  //       `<p class="aa-loading">Generating guided steps…</p>`;

  //     const issue = violations.find(v => v.id === issueId);
  //     const steps = await this._generateGuidedFix(issue);
  //     this._stepsCache[issueId] = steps;

  //     stepsPanel.querySelector(".aa-resolution-steps-list").innerHTML = steps;
  //     return;
  //   }

  //   // Apply AI fix
  //   if (aiBtn) {
  //     const issueId = aiBtn.dataset.issueId;
  //     const aiPanel = this.shadowRoot.querySelector(`#ai-success-${issueId}`);
  //     const stepsPanel = this.shadowRoot.querySelector(`#resolution-steps-${issueId}`);

  //     // 🔹 Toggle selected class
  //       aiBtn.classList.add("aa-selected");
  //       const stepsButton = this.shadowRoot.querySelector(`.aa-btn-steps[data-issue-id="${issueId}"]`);
  //       stepsButton?.classList.remove("aa-selected");

  //     stepsPanel.style.display = "none";
  //     aiPanel.style.display = "block";

  //     //const issue = violations.find(v => v.id === issueId);
  //   //   const result = await this._applyAutoFix(issue);
  //   //   aiPanel.insertAdjacentHTML("beforeend",
  //   //     `<pre class="aa-ai-result">${this.escapeHTML(JSON.stringify(result,null,2))}</pre>`);
  //   //   return;
  //   }

  //   // Save & Reload
  //   if (saveBtn) {
  //     const issueId = saveBtn.dataset.issueId;
  //     const issue = violations.find(v => v.id === issueId);
  //     await this._saveFix(issue);
  //     location.reload();
  //     return;
  //   }

  //   // Accept AI fix
  //   if (acceptBtn) {
  //     const issueId = acceptBtn.dataset.issueId;
  //     const issue = violations.find(v => v.id === issueId);
  //     await this._saveFix(issue);
  //     location.reload();
  //     return;
  //   }

  //   // Reject AI fix
  //   if (rejectBtn) {
  //     const issueId = rejectBtn.dataset.issueId;
  //     const aiPanel = this.shadowRoot.querySelector(`#ai-success-${issueId}`);
  //     aiPanel.style.display = "none";
  //     return;
  //   }
  // };

  // Hover highlight
  this._resultsMouseOverHandler = (e) => {
    const item = e.target.closest(".aa-accordion-item");
    if (!item) return;
    this._previewHighlightFromItem(item);
  };

  this._resultsMouseOutHandler = (e) => {
    const item = e.target.closest(".aa-accordion-item");
    if (!item) return;
    this._clearPreviewHighlight();
  };

  this.shadowRoot.addEventListener("click", this._resultsClickHandler);
  this.shadowRoot.addEventListener("mouseover", this._resultsMouseOverHandler);
  this.shadowRoot.addEventListener("mouseout", this._resultsMouseOutHandler);
}





/*****************************************************************
 * Score → Grade + Color mapping
 *****************************************************************/
_calculateGrade(score) {
  if (score >= 95) return "A";
  if (score >= 85) return "B";
  if (score >= 70) return "C";
  if (score >= 50) return "D";
  return "F";
}

_gradeColor(grade) {
  const map = {
    A: "#006400",  // dark green
    B: "#28a745",  // light green
    C: "#fd8e03",  // orange
    D: "#ff6b6b",  // light red
    F: "#8b0000",  // dark red
  };
  return map[grade] || "#8b0000";
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

  console.log(issue);

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
      body: JSON.stringify({ issue, post_id: aaEditor.postId })
    });

    if (!resp.ok) throw new Error(`Server returned ${resp.status}`);
      refreshBricksCanvas().then(res => console.log('refresh result', res));


    const data = await resp.json();

    // Store revision_key for later Accept/Reject
    this._lastRevisionKey = data.revision_key || null;

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
    const resp = await fetch(`${aaEditor.root}save-fix`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": aaEditor.restNonce,
      },
      body: JSON.stringify({ issue, post_id: aaEditor.postId, revision_key: this._lastRevisionKey })
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





async _highlightNode(node) {
  if (!node || !Array.isArray(node.target)) return;

  // Flatten selectors (axe gives target as array of arrays)
  let selectors = node.target.flat().filter(Boolean);

  // locate preview iframe & doc
  const previewIframe = document.querySelector("#bricks-builder-iframe") || document.querySelector("iframe");
  if (!previewIframe) return;
  let previewDoc;
  try { 
    previewDoc = previewIframe.contentDocument || previewIframe.contentWindow.document; 
  } catch(e) { 
    previewDoc = null; 
  }
  if (!previewDoc) return;

  // highlight first match
  for (const sel of selectors) {
    try {
      const el = previewDoc.querySelector(sel);
      if (!el) continue;

      // scroll into view
      try { el.scrollIntoView({ behavior: "smooth", block: "center" }); } catch(e){}

      // visually highlight
      this._applyPreviewOutline(el);

      // If Bricks element has id / brxId, tell parent
      const brxId = el.dataset?.brxId || el.getAttribute("data-brx-id") || el.id;
      if (brxId) {
        previewIframe.contentWindow.parent.postMessage(
          { type: "bricks.selectElement", id: brxId },
          "*"
        );
      }

      // stop after first match
      break;
    } catch (err) {
      console.warn("Error highlighting selector:", sel, err);
    }
  }
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
        //const opts = { runOnly: ["wcag2a", "wcag2aa", "wcag21aa"] };

        const opts = {
          type: "tag",
          runOnly: ["wcag2a", "wcag2aa", "wcag21aa"],
          
        };

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
      //this.updateAccessibilityUI(100);

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

  /**
 * Refresh Bricks Builder canvas (tries several strategies) and optionally focus/select element.
 * @param {Object} opts
 *   - selectElementId {string} optional element id (e.g. "brxe-e3aaf7" or "e3aaf7")
 *   - forceReload {boolean} optional: if true will force iframe src reload as fallback
 * @returns {Promise<{ok:boolean, method:string}>}
 */
async function  refreshBricksCanvas(opts = {}) {
  const { selectElementId, forceReload = true } = opts;

  // helper to normalise id (allow "e3aaf7" or "brxe-e3aaf7")
  const normalize = id => id ? (id.startsWith('brxe-') ? id : `brxe-${id}`) : null;

  const iframe = document.getElementById('bricks-builder-iframe') || document.querySelector('iframe#bricks-builder-iframe');

  // 1) If global BRICKS builder API exists in parent (most reliable)
  try {
    if (window.BRICKS && window.BRICKS.builder) {
      console.log( "tell bricks data changed (keeps hooks happy");
      // tell bricks data changed (keeps hooks happy)
      try { window.BRICKS.hooks.doAction && window.BRICKS.hooks.doAction('bricks.data.updated'); } catch(e){/*ignore*/}

      // If element selection requested and builder exposes update/select helpers
      if (selectElementId) {
        const sel = normalize(selectElementId);
        try {
          // prefer a builder method if available
          if (typeof window.BRICKS.builder.updateElement === 'function') {
            window.BRICKS.builder.updateElement(sel);
          }
          if (typeof window.BRICKS.builder.selectElement === 'function') {
            window.BRICKS.builder.selectElement(sel);
          }
        } catch (e) { /* ignore */ }
      }

      // reload iframe via builder API (does a proper refresh)
      if (typeof window.BRICKS.builder.reloadIframe === 'function') {
        await window.BRICKS.builder.reloadIframe();
        return { ok: true, method: 'BRICKS.builder.reloadIframe' };
      }

      // reload through builder.save? sometimes builder has other helpers
      if (typeof window.BRICKS.builder.refresh === 'function') {
        await window.BRICKS.builder.refresh();
        return { ok: true, method: 'BRICKS.builder.refresh' };
      }
    }
  } catch (err) {
    console.warn('BRICKS builder API attempt failed', err);
  }

  // 2) Try to call functions inside the iframe (if same-origin)
  if (iframe && iframe.contentWindow) {
    try {
      // If iframe exposes BRICKS in its window, call reload/select there
      const iw = iframe.contentWindow;

      if (iw.BRICKS && iw.BRICKS.builder) {
        
        try { iw.BRICKS.hooks.doAction && iw.BRICKS.hooks.doAction('bricks.data.updated'); } catch(e){/*ignore*/}

        if (selectElementId) {
          const sel = normalize(selectElementId);
          try {
            if (typeof iw.BRICKS.builder.updateElement === 'function') {
              iw.BRICKS.builder.updateElement(sel);
            }
            if (typeof iw.BRICKS.builder.selectElement === 'function') {
              iw.BRICKS.builder.selectElement(sel);
            }
          } catch (e) { /*ignore*/ }
        }

        if (typeof iw.BRICKS.builder.reloadIframe === 'function') {
          iw.BRICKS.builder.reloadIframe();
          return { ok: true, method: 'iframe.BRICKS.builder.reloadIframe' };
        }
      }

      // 2b) Send a postMessage the iframe might listen for (Bricks listens to some msg types)
      try {
        const sel = normalize(selectElementId);
        if (sel) {
          iw.postMessage({ type: 'bricks.selectElement', id: sel }, '*');
        }
        // generic reload message (some integrations may respond)
        iw.postMessage({ type: 'bricks.reload' }, '*');

        return { ok: true, method: 'iframe.postMessage' };
      } catch (err) {
        // fall through to last resort
      }
    } catch (err) {
      console.warn('iframe invocation failed (maybe cross-origin)', err);
    }
  }

  // 3) Last resort: force iframe src reload (works even without builder hooks)
  if (iframe && iframe.src) {
    try {
      // add a cache-busting param so the browser fetches fresh data
      const src = iframe.getAttribute('src') || iframe.src;
      const base = src.split('?')[0];
      iframe.src = base + '?_aa_refresh=' + Date.now();
      return { ok: true, method: 'iframe.src_reload' };
    } catch (err) {
      console.warn('iframe.src reload failed', err);
    }
  }

  // nothing worked
  return { ok: false, method: 'none' };
}



// register component
customElements.define('aa-dashboard', AADashboard);

// auto-insert into top window
document.addEventListener('DOMContentLoaded', () => {
  if (window.self === window.top && !document.querySelector('aa-dashboard')) {
    document.body.appendChild(document.createElement('aa-dashboard'));
  }
});
