import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright configuration for the MarkMajorChanges extension e2e tests.
 *
 * Run:
 *   npx playwright test
 *
 * Environment variables:
 *   MW_BASE_URL     - MediaWiki base URL (default: http://localhost:8082)
 *   MW_SCRIPT_PATH  - path prefix to the wiki (default: /he)
 *   MW_ARTICLE_PATH - a content page to check the action on (default: the wiki root)
 *   MW_USERNAME     - a user in the `staff` group (holds markmajorchange + changetags)
 *   MW_PASSWORD     - that user's password
 *
 * The "mark major change" toolbar action only shows for a staff user, so the
 * authenticated specs skip cleanly when MW_USERNAME/MW_PASSWORD are unset.
 */
export default defineConfig({
  testDir: './e2e',
  testMatch: ['**/*.spec.ts'],
  timeout: 30000,
  expect: { timeout: 7000 },
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: 1,

  reporter: [
    ['html', { outputFolder: 'playwright-report', open: 'never' }],
    ['list'],
  ],

  use: {
    baseURL: process.env.MW_BASE_URL || 'http://localhost:8082',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },

  projects: [
    {
      name: 'markmajorchanges-desktop',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1440, height: 900 },
      },
    },
  ],
});
