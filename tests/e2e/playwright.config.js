import { defineConfig } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import dotenv from 'dotenv';

const here = path.dirname(fileURLToPath(import.meta.url));
const authDir = path.join(here, '.auth');
const authFile = path.join(authDir, 'wp-admin.json');

if (!fs.existsSync(authDir)) {
  fs.mkdirSync(authDir, { recursive: true });
}

const labEnvPath = path.resolve(here, '../../../wp-whittemore-lab/.env');
if (fs.existsSync(labEnvPath)) {
  dotenv.config({ path: labEnvPath });
}

const baseURL = process.env.WP_URL || 'http://localhost:8090';
const adminUser = process.env.WP_ADMIN_USER || 'admin';
const adminPass = process.env.WP_ADMIN_PASSWORD || 'admin12345';

export default defineConfig({
  testDir: path.join(here, 'tests'),
  timeout: 60_000,
  expect: { timeout: 10_000 },
  fullyParallel: false,
  retries: 0,
  reporter: [['list']],
  use: {
    baseURL,
    headless: false,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure'
  },
  projects: [
    {
      name: 'auth-setup',
      testMatch: /auth\.setup\.spec\.js/
    },
    {
      name: 'chromium',
      testIgnore: /auth\.setup\.spec\.js/,
      use: {
        browserName: 'chromium',
        storageState: authFile
      },
      dependencies: ['auth-setup']
    }
  ],
  metadata: {
    baseURL,
    adminUser,
    hasAdminPass: Boolean(adminPass)
  }
});

export { authFile, baseURL, adminUser, adminPass };
