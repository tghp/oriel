import { defineConfig, devices } from '@playwright/test';

const HTTP_PORT = process.env.ORIEL_HTTP_PORT ?? '8788';

export default defineConfig({
  testDir: './e2e',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: 0,
  reporter: process.env.CI ? 'github' : 'list',
  globalSetup: './support/global-setup',
  use: {
    baseURL: `http://localhost:${HTTP_PORT}`,
    trace: 'retain-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
