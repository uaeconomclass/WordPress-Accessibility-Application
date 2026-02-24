import { test, expect } from '@playwright/test';

const FIXTURE_PAGES = [
  { id: 31, name: 'Frame Title' },
  { id: 29, name: 'Link Name' },
  { id: 51, name: 'Button Name' }
];

test.describe('Accessibility Auditor Bricks smoke', () => {
  for (const fixture of FIXTURE_PAGES) {
    test(`panel mounts on ${fixture.name} fixture (#${fixture.id})`, async ({ page, baseURL }) => {
      await page.goto(`${baseURL}/?page_id=${fixture.id}&bricks=run`, { waitUntil: 'domcontentloaded' });

      const panel = page.locator('aa-dashboard');
      await expect(panel).toHaveCount(1);
      // Initial state renders launcher button only (renderButton), not the full panel.
      await expect
        .poll(async () => panel.evaluate((el) => {
          const root = el.shadowRoot;
          if (!root) return false;
          return !!root.querySelector('#aa-accessibility-btn');
        }))
        .toBeTruthy();

      // Open the panel via the known launcher button inside the component shadow DOM.
      const clicked = await panel.evaluate((el) => {
        const root = el.shadowRoot;
        if (!root) return false;
        const target = root.querySelector('#aa-accessibility-btn');
        if (!(target instanceof HTMLButtonElement)) return false;
        target.click();
        return true;
      });

      expect(clicked).toBeTruthy();

      // After click, renderPanel() replaces shadow DOM with the full UI.
      await expect
        .poll(async () => panel.evaluate((el) => {
          const root = el.shadowRoot;
          if (!root) return false;
          const title = root.querySelector('.aa-panel-title')?.textContent || '';
          const scoreLabel = root.querySelector('.aa-score-label')?.textContent || '';
          const runScanBtn = root.querySelector('#aa-run-scan');
          return (
            title.toUpperCase().includes('ACCESSIBILITY') &&
            scoreLabel.toLowerCase().includes('page score') &&
            !!runScanBtn
          );
        }))
        .toBeTruthy();
    });
  }
});
