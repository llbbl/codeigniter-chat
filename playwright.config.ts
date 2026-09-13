import fs from 'node:fs';
import { defineConfig, devices } from '@playwright/test';
import { applicationEnvironment, baseURL, cachePath, rootDir } from './e2e/support/environment';

fs.mkdirSync(cachePath, { recursive: true });
const reuseExistingServer = process.env.PLAYWRIGHT_REUSE_SERVER === '1';

export default defineConfig({
  testDir: './e2e',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  timeout: 30_000,
  expect: { timeout: 7_500 },
  globalSetup: './e2e/global-setup.ts',
  reporter: process.env.CI ? [['line'], ['html', { open: 'never' }]] : 'list',
  use: {
    baseURL,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
  webServer: [
    {
      command: 'php spark serve --host 127.0.0.1 --port 8085',
      cwd: rootDir,
      env: applicationEnvironment,
      port: 8085,
      reuseExistingServer,
      timeout: 30_000,
    },
    {
      command: 'pnpm dev --host 127.0.0.1 --port 5173 --strictPort',
      cwd: rootDir,
      port: 5173,
      reuseExistingServer,
      timeout: 30_000,
    },
  ],
});
