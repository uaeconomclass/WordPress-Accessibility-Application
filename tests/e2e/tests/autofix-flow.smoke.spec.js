import { test, expect } from '@playwright/test';
import { execSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const LAB_DIR = path.resolve(HERE, '../../../../wp-whittemore-lab');

function reseedFixtures() {
  execSync(
    'docker compose run --rm wpcli eval-file /var/www/html/wp-content/plugins/accessibility-auditor/scripts/seed_bricks_test_pages.php --path=/var/www/html --allow-root',
    { cwd: LAB_DIR, stdio: 'inherit', shell: true }
  );
}

const SHOULD_PAUSE_ON_ERROR = process.env.E2E_PAUSE_ON_ERROR === '1' || Boolean(process.env.PWDEBUG);

async function readPanelState(panel) {
  return panel.evaluate((el) => {
    const root = el.shadowRoot;
    if (!root) {
      return { state: 'no-root', text: '', open: false, hasLauncher: false, isScanning: false };
    }

    const container = root.querySelector('#aa-scan-results');
    const text = (container?.textContent || '').trim();
    const lower = text.toLowerCase();
    const open = !!root.querySelector('.aa-panel-title') && !!container;
    const hasLauncher = !!root.querySelector('#aa-accessibility-btn');
    const isScanning = !!el._isScanning;

    let state = 'idle';
    if (isScanning || lower.includes('scanning with axe-core')) state = 'scanning';
    else if (root.querySelector('.aa-accordion-item') || lower.includes('generate resolution steps') || lower.includes('fix with ai')) state = 'issues';
    else if (lower.includes('no issues found')) state = 'no-issues';
    else if (lower.includes('needs review')) state = 'needs-review';
    else if (lower.includes('error:')) state = 'error';
    else if (lower.includes('preview iframe not found')) state = 'preview-missing';
    else if (lower.includes('preview document not accessible')) state = 'preview-inaccessible';

    return { state, text, open, hasLauncher, isScanning };
  });
}

async function openAccessibilityPanel(page) {
  const panel = page.locator('aa-dashboard');
  await expect(panel).toHaveCount(1);

  for (let i = 0; i < 8; i += 1) {
    const state = await readPanelState(panel);
    if (state.open) {
      return panel;
    }

    if (!state.hasLauncher) {
      await page.waitForTimeout(500);
      continue;
    }

    await page.waitForTimeout(300);
    await page.locator('#bricks-preloader.show').waitFor({ state: 'hidden', timeout: 1500 }).catch(() => {});
    const clicked = await page.locator('aa-dashboard').evaluate((el) => {
      const button = el.shadowRoot?.querySelector('#aa-accessibility-btn');
      if (!(button instanceof HTMLElement)) return false;
      button.click();
      return true;
    }).catch(() => false);
    if (!clicked) continue;
    const opened = await expect.poll(async () => {
      const inner = await readPanelState(panel);
      return inner.open;
    }, { timeout: 3_500 }).toBeTruthy().then(() => true).catch(() => false);
    if (opened) return panel;
  }

  await expect.poll(async () => {
    const state = await readPanelState(panel);
    return state.open;
  }).toBeTruthy();

  return panel;
}

async function runAutoFixSmoke(page, baseURL, pageId) {
  await page.goto(`${baseURL}/?page_id=${pageId}&bricks=run`, { waitUntil: 'domcontentloaded' });

  const panel = await openAccessibilityPanel(page);

  await expect
    .poll(async () => page.evaluate(() => {
      const iframe = document.querySelector('#bricks-builder-iframe, iframe');
      if (!(iframe instanceof HTMLIFrameElement)) return 'no-iframe';
      try {
        const doc = iframe.contentDocument;
        return doc?.readyState || 'no-doc';
      } catch {
        return 'cross-origin';
      }
    }), { timeout: 20_000 })
    .toBe('complete');

  await page.locator('aa-dashboard').evaluate(async (el) => {
    if (typeof el.runScan === 'function') {
      await el.runScan(false);
    }
  });

  await expect.poll(async () => {
    const state = await readPanelState(panel);
    return state.isScanning ? 'scanning' : state.state;
  }, { timeout: 30_000 }).not.toBe('scanning');

  const scanStateData = await readPanelState(panel);
  const scanState = scanStateData.state;

  if (scanState === 'unknown') {
    console.log('SCAN_UNKNOWN_DEBUG', { pageId, ...scanStateData });
  }

  expect(scanState).toBe('issues');

  const firstAccordionBtn = page.locator('aa-dashboard').locator('.aa-accordion-item .aa-accordion-btn').first();
  await expect(firstAccordionBtn).toHaveCount(1);
  const issueTitleBefore = ((await firstAccordionBtn.textContent()) || '').trim();
  await firstAccordionBtn.click();

  const firstAiBtn = page.locator('aa-dashboard').locator('.aa-btn-ai-fix').first();
  await expect(firstAiBtn).toHaveCount(1);
  const issueId = await firstAiBtn.getAttribute('data-issue-id');
  const autoFixResponsePromise = page.waitForResponse((resp) => {
    return resp.url().includes('/aa/v1/auto-fix') && resp.request().method() === 'POST';
  }, { timeout: 30_000 });
  await firstAiBtn.click();

  expect(issueId).toBeTruthy();

  const autoFixResp = await autoFixResponsePromise;
  expect(autoFixResp.status()).toBe(200);
  const autoFixJson = await autoFixResp.json();
  expect(autoFixJson).toBeTruthy();

  const aiStatePoll = expect.poll(async () => panel.evaluate((el, issueIdArg) => {
    const root = el.shadowRoot;
    if (!root || !issueIdArg) return { state: 'missing', text: '' };
    const aiPanel = root.querySelector(`#ai-success-${issueIdArg}`);
    const aiText = aiPanel?.querySelector('.aa-ai-text');
    const txt = (aiText?.textContent || '').trim();
    if (!txt) return { state: 'empty', text: '' };
    if (/Fixing with AI/i.test(txt)) return { state: 'loading', text: txt };
    if (/AI CHANGE MADE SUCCESSFULLY/i.test(txt)) return { state: 'success', text: txt };
    if (/Fix applied! Reloading editor/i.test(txt)) return { state: 'reload', text: txt };
    if (/AI Fix failed:/i.test(txt)) return { state: 'error', text: txt };
    return { state: 'done', text: txt };
  }, issueId), { timeout: 40_000 });

  await aiStatePoll.not.toMatchObject({ state: 'loading' });
  let aiState = await panel.evaluate((el, issueIdArg) => {
    const root = el.shadowRoot;
    if (!root || !issueIdArg) return { state: 'missing', text: '' };
    const aiPanel = root.querySelector(`#ai-success-${issueIdArg}`);
    const aiText = aiPanel?.querySelector('.aa-ai-text');
    const txt = (aiText?.textContent || '').trim();
    if (!txt) return { state: 'empty', text: '' };
    if (/Fixing with AI/i.test(txt)) return { state: 'loading', text: txt };
    if (/AI CHANGE MADE SUCCESSFULLY/i.test(txt)) return { state: 'success', text: txt };
    if (/Fix applied! Reloading editor/i.test(txt)) return { state: 'reload', text: txt };
    if (/AI Fix failed:/i.test(txt)) return { state: 'error', text: txt };
    return { state: 'done', text: txt };
  }, issueId);

  if (aiState.state === 'empty') {
    const scanBannerText = await panel.evaluate((el) => {
      const root = el.shadowRoot;
      const container = root?.querySelector('#aa-scan-results');
      return (container?.textContent || '').trim();
    });
    if (/Fix applied! Reloading editor/i.test(scanBannerText)) {
      aiState = { state: 'reload', text: scanBannerText };
    }
  }

  expect(['success', 'done', 'reload']).toContain(aiState.state);
  // If the editor reloads, the web component may remount. Re-open panel and rescan to verify improvement.
  if (aiState.state === 'reload') {
    await page.waitForLoadState('domcontentloaded', { timeout: 20_000 }).catch(() => {});
    await page.waitForTimeout(2_000);
  }

  const panelAfter = await openAccessibilityPanel(page);
  await page.locator('aa-dashboard').evaluate(async (el) => {
    if (typeof el.runScan === 'function') {
      await el.runScan(false);
    }
  });

  await expect.poll(async () => {
    const state = await readPanelState(panelAfter);
    return state.isScanning ? 'scanning' : state.state;
  }, { timeout: 30_000 }).not.toBe('scanning');

  const postScan = await panelAfter.evaluate((el) => {
    const root = el.shadowRoot;
    const container = root?.querySelector('#aa-scan-results');
    const txt = (container?.textContent || '').toLowerCase();
    const issueTitles = Array.from(root?.querySelectorAll('.aa-accordion-item .aa-accordion-btn') || [])
      .map((n) => (n.textContent || '').trim())
      .filter(Boolean);
    if (root?.querySelector('.aa-accordion-item') || txt.includes('generate resolution steps') || txt.includes('fix with ai')) {
      return { state: 'issues', text: txt, issueTitles };
    }
    if (txt.includes('no issues found')) return { state: 'no-issues', text: txt, issueTitles };
    if (txt.includes('needs review')) return { state: 'needs-review', text: txt, issueTitles };
    if (txt.includes('error:')) return { state: 'error', text: txt, issueTitles };
    return { state: 'unknown', text: txt, issueTitles };
  });

  expect(['no-issues', 'needs-review', 'issues']).toContain(postScan.state);
  if (postScan.state === 'issues' && issueTitleBefore) {
    expect(postScan.issueTitles).not.toContain(issueTitleBefore);
  }

  return { issueId, aiState, autoFixJson, issueTitleBefore, postScan };
}

async function runGuidedFallbackSmoke(page, baseURL, pageId) {
  await page.goto(`${baseURL}/?page_id=${pageId}&bricks=run`, { waitUntil: 'domcontentloaded' });
  const panel = await openAccessibilityPanel(page);

  await page.locator('aa-dashboard').evaluate(async (el) => {
    if (typeof el.runScan === 'function') {
      await el.runScan(false);
    }
  });

  await expect.poll(async () => {
    const state = await readPanelState(panel);
    return state.isScanning ? 'scanning' : state.state;
  }, { timeout: 30_000 }).not.toBe('scanning');

  const firstAccordionBtn = page.locator('aa-dashboard').locator('.aa-accordion-item .aa-accordion-btn').first();
  await expect(firstAccordionBtn).toHaveCount(1);
  await firstAccordionBtn.click();

  const firstAiBtn = page.locator('aa-dashboard').locator('.aa-btn-ai-fix').first();
  await expect(firstAiBtn).toHaveCount(1);

  const autoFixResponsePromise = page.waitForResponse((resp) => (
    resp.url().includes('/aa/v1/auto-fix') && resp.request().method() === 'POST'
  ), { timeout: 30_000 });
  await firstAiBtn.click();
  const autoFixResp = await autoFixResponsePromise;
  expect(autoFixResp.status()).toBe(200);
  const autoFixJson = await autoFixResp.json();

  // Guided fallback still returns success payload, but UI should show generated instructions text.
  const aiText = await expect.poll(async () => panel.evaluate((el) => {
    const root = el.shadowRoot;
    const ai = root?.querySelector('.aa-ai-text');
    return (ai?.textContent || '').trim();
  }), { timeout: 40_000 }).not.toMatch(/Fixing with AI/i).then(async () => {
    return panel.evaluate((el) => {
      const root = el.shadowRoot;
      return (root?.querySelector('.aa-ai-text')?.textContent || '').trim();
    });
  });

  expect(aiText.length).toBeGreaterThan(20);
  return { autoFixJson, aiText };
}

async function runNeedsReviewSmoke(page, baseURL, pageId) {
  await page.goto(`${baseURL}/?page_id=${pageId}&bricks=run`, { waitUntil: 'domcontentloaded' });
  const panel = await openAccessibilityPanel(page);
  await page.locator('aa-dashboard').evaluate(async (el) => {
    if (typeof el.runScan === 'function') {
      await el.runScan(false);
    }
  });

  await expect.poll(async () => {
    const state = await readPanelState(panel);
    return state.isScanning ? 'scanning' : state.state;
  }, { timeout: 30_000 }).not.toBe('scanning');

  const state = await panel.evaluate((el) => {
    const root = el.shadowRoot;
    const txt = (root?.querySelector('#aa-scan-results')?.textContent || '').toLowerCase();
    if (txt.includes('needs review')) return 'needs-review';
    if (root?.querySelector('.aa-accordion-item')) return 'issues';
    if (txt.includes('no issues found')) return 'no-issues';
    return 'unknown';
  });

  expect(['needs-review', 'issues']).toContain(state);
  return { state };
}

test.describe('Accessibility Auditor auto-fix flow smoke', () => {
  test.setTimeout(180_000);
  test.beforeEach(() => {
    reseedFixtures();
  });

  test('Frame Title fixture (#1321): scan -> issue list -> fix with AI', async ({ page, baseURL }) => {
    try {
      await runAutoFixSmoke(page, baseURL, 1321);
    } catch (err) {
      const debugState = await page.evaluate(() => {
        const host = document.querySelector('aa-dashboard');
        const root = host?.shadowRoot;
        const container = root?.querySelector('#aa-scan-results');
        return {
          location: window.location.href,
          hasDashboard: !!host,
          hasShadow: !!root,
          hasLauncher: !!root?.querySelector('#aa-accessibility-btn'),
          hasPanelTitle: !!root?.querySelector('.aa-panel-title'),
          scanText: (container?.textContent || '').trim(),
          scanHtml: container?.innerHTML || ''
        };
      }).catch(() => ({ evalFailed: true }));
      console.error('AUTOFIX_FLOW_DEBUG', debugState);
      console.error('AUTOFIX_FLOW_ERROR', err?.message || String(err));

      // Keep browser open so we can inspect the exact broken state instead of instantly closing.
      if (SHOULD_PAUSE_ON_ERROR) {
        await page.pause();
        return;
      }
      throw err;
    }
  });

  test('Link Name fixture (#1319): scan -> issue list -> fix with AI', async ({ page, baseURL }) => {
    try {
      await runAutoFixSmoke(page, baseURL, 1319);
    } catch (err) {
      const debugState = await page.evaluate(() => {
        const host = document.querySelector('aa-dashboard');
        const root = host?.shadowRoot;
        const container = root?.querySelector('#aa-scan-results');
        return {
          location: window.location.href,
          hasDashboard: !!host,
          hasShadow: !!root,
          hasLauncher: !!root?.querySelector('#aa-accessibility-btn'),
          hasPanelTitle: !!root?.querySelector('.aa-panel-title'),
          scanText: (container?.textContent || '').trim(),
          scanHtml: container?.innerHTML || ''
        };
      }).catch(() => ({ evalFailed: true }));
      console.error('AUTOFIX_FLOW_DEBUG', debugState);
      console.error('AUTOFIX_FLOW_ERROR', err?.message || String(err));
      if (SHOULD_PAUSE_ON_ERROR) {
        await page.pause();
        return;
      }
      throw err;
    }
  });

  test('Image Alt fixture (#1317): scan -> issue list -> fix with AI', async ({ page, baseURL }) => {
    try {
      await runAutoFixSmoke(page, baseURL, 1317);
    } catch (err) {
      console.error('AUTOFIX_FLOW_ERROR', err?.message || String(err));
      if (SHOULD_PAUSE_ON_ERROR) {
        await page.pause();
        return;
      }
      throw err;
    }
  });

  test('role-img-alt fixture (#1342): guided fallback response', async ({ page, baseURL }) => {
    try {
      await runGuidedFallbackSmoke(page, baseURL, 1342);
    } catch (err) {
      console.error('GUIDED_FLOW_ERROR', err?.message || String(err));
      if (SHOULD_PAUSE_ON_ERROR) {
        await page.pause();
        return;
      }
      throw err;
    }
  });

  test('Color Contrast fixture (#1333): needs review or issue list renders', async ({ page, baseURL }) => {
    try {
      await runNeedsReviewSmoke(page, baseURL, 1333);
    } catch (err) {
      console.error('NEEDS_REVIEW_FLOW_ERROR', err?.message || String(err));
      if (SHOULD_PAUSE_ON_ERROR) {
        await page.pause();
        return;
      }
      throw err;
    }
  });

  test('Scenario sweep: run all key fixtures sequentially in one browser', async ({ page, baseURL }) => {
    const results = [];
    const scenarios = [
      { name: 'frame-title', run: () => runAutoFixSmoke(page, baseURL, 1321) },
      { name: 'link-name', run: () => runAutoFixSmoke(page, baseURL, 1319) },
      { name: 'image-alt', run: () => runAutoFixSmoke(page, baseURL, 1317) },
      { name: 'role-img-alt-guided', run: () => runGuidedFallbackSmoke(page, baseURL, 1342) },
      { name: 'color-contrast-needs-review', run: () => runNeedsReviewSmoke(page, baseURL, 1333) },
    ];

    for (const scenario of scenarios) {
      try {
        const result = await scenario.run();
        results.push({ name: scenario.name, ok: true, result });
      } catch (err) {
        results.push({ name: scenario.name, ok: false, error: err?.message || String(err) });
        if (SHOULD_PAUSE_ON_ERROR) {
          console.error('SCENARIO_SWEEP_FAIL', results);
          await page.pause();
          return;
        }
        throw err;
      }
    }
    console.log('SCENARIO_SWEEP_RESULTS', results.map(r => ({ name: r.name, ok: r.ok })));
  });
});
