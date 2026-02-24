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

async function openAccessibilityPanel(page) {
  const panel = page.locator('aa-dashboard');
  await expect(panel).toHaveCount(1);

  await expect
    .poll(async () => panel.evaluate((el) => !!el.shadowRoot?.querySelector('#aa-accessibility-btn')))
    .toBeTruthy();

  const launcherBtn = page.locator('aa-dashboard').locator('#aa-accessibility-btn');
  await expect(launcherBtn).toHaveCount(1);
  for (let i = 0; i < 5; i += 1) {
    await page.waitForTimeout(300);
    await page.locator('#bricks-preloader.show').waitFor({ state: 'hidden', timeout: 1500 }).catch(() => {});
    const clicked = await launcherBtn.click({ force: true }).then(() => true).catch(() => false);
    if (!clicked) continue;
    const opened = await expect
      .poll(async () => panel.evaluate((el) => {
        const root = el.shadowRoot;
        return !!root?.querySelector('.aa-panel-title') && !!root?.querySelector('#aa-run-scan');
      }), { timeout: 2_500 })
      .toBeTruthy()
      .then(() => true)
      .catch(() => false);
    if (opened) return panel;
  }

  await expect
    .poll(async () => panel.evaluate((el) => {
      const root = el.shadowRoot;
      if (!root) return false;
      return !!root.querySelector('.aa-panel-title') && !!root.querySelector('#aa-run-scan');
    }))
    .toBeTruthy();

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

  const runScanBtn = page.locator('aa-dashboard').locator('#aa-run-scan');
  await expect(runScanBtn).toHaveCount(1);
  await runScanBtn.scrollIntoViewIfNeeded();
  for (let i = 0; i < 3; i += 1) {
    await runScanBtn.click({ force: true });
    const started = await expect.poll(async () => panel.evaluate((el) => {
      const root = el.shadowRoot;
      const container = root?.querySelector('#aa-scan-results');
      const txt = (container?.textContent || '').toLowerCase();
      return txt.includes('scanning with axe-core') || txt.includes('needs review') || txt.includes('no issues found') || !!root?.querySelector('.aa-accordion-item');
    }), { timeout: 5_000 }).toBeTruthy().then(() => true).catch(() => false);
    if (started) break;
  }

  const scanStatePoll = expect.poll(async () => panel.evaluate((el) => {
    const root = el.shadowRoot;
    if (!root) return 'no-root';
    const container = root.querySelector('#aa-scan-results');
    if (!container) return 'no-container';
    const txt = (container.textContent || '').toLowerCase();
    if (txt.includes('scanning with axe-core')) return 'scanning';
    if (root.querySelector('.aa-accordion-item') || txt.includes('generate resolution steps') || txt.includes('fix with ai')) return 'issues';
    if (txt.includes('no issues found')) return 'no-issues';
    if (txt.includes('needs review')) return 'needs-review';
    if (txt.includes('error:')) return 'error';
    if (txt.includes('preview iframe not found')) return 'preview-missing';
    if (txt.includes('preview document not accessible')) return 'preview-inaccessible';
    return 'unknown';
  }), { timeout: 20_000 });

  await scanStatePoll.not.toBe('scanning');
  const scanState = await panel.evaluate((el) => {
    const root = el.shadowRoot;
    if (!root) return 'no-root';
    const container = root.querySelector('#aa-scan-results');
    if (!container) return 'no-container';
    const txt = (container.textContent || '').toLowerCase();
    if (root.querySelector('.aa-accordion-item') || txt.includes('generate resolution steps') || txt.includes('fix with ai')) return 'issues';
    if (txt.includes('no issues found')) return 'no-issues';
    if (txt.includes('needs review')) return 'needs-review';
    if (txt.includes('error:')) return 'error';
    if (txt.includes('preview iframe not found')) return 'preview-missing';
    if (txt.includes('preview document not accessible')) return 'preview-inaccessible';
    if (txt.includes('scanning with axe-core')) return 'scanning';
    return 'unknown';
  });

  if (scanState === 'unknown') {
    const debug = await panel.evaluate((el) => {
      const root = el.shadowRoot;
      const container = root?.querySelector('#aa-scan-results');
      return {
        text: (container?.textContent || '').trim(),
        html: container?.innerHTML || '',
        hasAccordion: !!root?.querySelector('.aa-accordion-item')
      };
    });
    console.log('SCAN_UNKNOWN_DEBUG', { pageId, ...debug });
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
  const runScanBtnAfter = page.locator('aa-dashboard').locator('#aa-run-scan');
  await expect(runScanBtnAfter).toHaveCount(1);
  await runScanBtnAfter.click({ force: true });

  await expect.poll(async () => panelAfter.evaluate((el) => {
    const root = el.shadowRoot;
    const container = root?.querySelector('#aa-scan-results');
    const txt = (container?.textContent || '').toLowerCase();
    if (txt.includes('scanning with axe-core')) return 'scanning';
    if (root?.querySelector('.aa-accordion-item') || txt.includes('generate resolution steps') || txt.includes('fix with ai')) return 'issues';
    if (txt.includes('no issues found')) return 'no-issues';
    if (txt.includes('needs review')) return 'needs-review';
    if (txt.includes('error:')) return 'error';
    return 'unknown';
  }), { timeout: 30_000 }).not.toBe('scanning');

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

  const runScanBtn = page.locator('aa-dashboard').locator('#aa-run-scan');
  await expect(runScanBtn).toHaveCount(1);
  await runScanBtn.click({ force: true });

  await expect.poll(async () => panel.evaluate((el) => {
    const root = el.shadowRoot;
    const txt = (root?.querySelector('#aa-scan-results')?.textContent || '').toLowerCase();
    if (txt.includes('scanning with axe-core')) return 'scanning';
    if (root?.querySelector('.aa-accordion-item') || txt.includes('fix with ai')) return 'issues';
    if (txt.includes('no issues found')) return 'no-issues';
    if (txt.includes('needs review')) return 'needs-review';
    return 'unknown';
  }), { timeout: 30_000 }).not.toBe('scanning');

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
  const runScanBtn = page.locator('aa-dashboard').locator('#aa-run-scan');
  await runScanBtn.click({ force: true });

  await expect.poll(async () => panel.evaluate((el) => {
    const root = el.shadowRoot;
    const txt = (root?.querySelector('#aa-scan-results')?.textContent || '').toLowerCase();
    if (txt.includes('scanning with axe-core')) return 'scanning';
    if (txt.includes('needs review')) return 'needs-review';
    if (root?.querySelector('.aa-accordion-item')) return 'issues';
    if (txt.includes('no issues found')) return 'no-issues';
    return 'unknown';
  }), { timeout: 30_000 }).not.toBe('scanning');

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

  test('Frame Title fixture (#31): scan -> issue list -> fix with AI', async ({ page, baseURL }) => {
    try {
      await runAutoFixSmoke(page, baseURL, 31);
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

  test('Link Name fixture (#29): scan -> issue list -> fix with AI', async ({ page, baseURL }) => {
    try {
      await runAutoFixSmoke(page, baseURL, 29);
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

  test('Button Name fixture (#51): scan -> issue list -> fix with AI', async ({ page, baseURL }) => {
    try {
      await runAutoFixSmoke(page, baseURL, 51);
    } catch (err) {
      console.error('AUTOFIX_FLOW_ERROR', err?.message || String(err));
      if (SHOULD_PAUSE_ON_ERROR) {
        await page.pause();
        return;
      }
      throw err;
    }
  });

  test('role-img-alt fixture (#52): guided fallback response', async ({ page, baseURL }) => {
    try {
      await runGuidedFallbackSmoke(page, baseURL, 52);
    } catch (err) {
      console.error('GUIDED_FLOW_ERROR', err?.message || String(err));
      if (SHOULD_PAUSE_ON_ERROR) {
        await page.pause();
        return;
      }
      throw err;
    }
  });

  test('Color Contrast fixture (#30): needs review or issue list renders', async ({ page, baseURL }) => {
    try {
      await runNeedsReviewSmoke(page, baseURL, 30);
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
      { name: 'frame-title', run: () => runAutoFixSmoke(page, baseURL, 31) },
      { name: 'link-name', run: () => runAutoFixSmoke(page, baseURL, 29) },
      { name: 'button-name', run: () => runAutoFixSmoke(page, baseURL, 51) },
      { name: 'role-img-alt-guided', run: () => runGuidedFallbackSmoke(page, baseURL, 52) },
      { name: 'color-contrast-needs-review', run: () => runNeedsReviewSmoke(page, baseURL, 30) },
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
