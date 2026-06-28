import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests',
  fullyParallel: false,
  retries: 1,
  reporter: [['html'], ['line']],
  use: {
    baseURL: 'http://localhost:8080',
    storageState: 'tests/.auth/admin.json',
    screenshot: 'only-on-failure',
    trace: 'on-first-retry',
  },
  projects: [
    {
      name: 'setup',
      testMatch: /global-setup\.ts/,
      use: { storageState: undefined },
    },
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
      dependencies: ['setup'],
    },
  ],
  globalSetup: './tests/global-setup.ts',
});
