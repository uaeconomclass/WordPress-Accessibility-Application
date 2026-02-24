import { test, expect } from '@playwright/test';
import { authFile, adminUser, adminPass } from '../playwright.config.js';

test('login and persist wp-admin session', async ({ page, baseURL }) => {
  await page.goto(`${baseURL}/wp-login.php`);

  await page.getByLabel('Username or Email Address').fill(adminUser);
  await page.locator('#user_pass').fill(adminPass);
  await page.getByRole('button', { name: /log in/i }).click();

  await expect(page).toHaveURL(/wp-admin/);
  await expect(page.locator('#wpadminbar')).toBeVisible();

  await page.context().storageState({ path: authFile });
});
