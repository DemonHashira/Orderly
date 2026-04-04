import { defineConfig } from '@playwright/test'

const port = Number(process.env.PLAYWRIGHT_PORT ?? 5173)
const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? `http://127.0.0.1:${port}`
const apiPort = Number(process.env.PLAYWRIGHT_API_PORT ?? 8000)
const fullStackMode = process.env.PLAYWRIGHT_MODE === 'full-stack'

export default defineConfig({
  testDir: './e2e',
  outputDir: './output/playwright/test-results',
  fullyParallel: false,
  workers: 1,
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI
    ? [['list'], ['html', { open: 'never', outputFolder: 'output/playwright/report' }]]
    : 'list',
  use: {
    baseURL,
    testIdAttribute: 'data-test',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  webServer: process.env.PLAYWRIGHT_SKIP_WEBSERVER
    ? undefined
    : [
        ...(fullStackMode
          ? [
              {
                command: './scripts/playwright-serve.sh',
                cwd: '../api',
                env: {
                  ...process.env,
                  PLAYWRIGHT_API_HOST: '127.0.0.1',
                  PLAYWRIGHT_API_PORT: String(apiPort),
                },
                url: `http://127.0.0.1:${apiPort}`,
                timeout: 180_000,
                reuseExistingServer: false,
              },
            ]
          : []),
        {
          command: `npm run dev -- --host 127.0.0.1 --port ${port}`,
          url: baseURL,
          timeout: 120_000,
          reuseExistingServer: !process.env.CI,
        },
      ],
})
