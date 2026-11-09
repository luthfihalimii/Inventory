import { defineConfig, devices } from '@playwright/test';

const database = process.env.E2E_DB || 'ent_inventory_e2e';
const baseURL = 'http://127.0.0.1:8123';

export default defineConfig({
    testDir: './e2e',
    globalSetup: './e2e/global-setup.ts',
    fullyParallel: false,
    workers: 1,
    retries: process.env.CI ? 2 : 0,
    reporter: process.env.CI ? 'github' : 'list',
    use: {
        baseURL,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
    webServer: {
        command: 'php artisan serve --host=127.0.0.1 --port=8123',
        // /up does not touch the database, so it is ready before globalSetup creates it.
        url: `${baseURL}/up`,
        reuseExistingServer: !process.env.CI,
        timeout: 120_000,
        env: {
            ...process.env,
            APP_ENV: 'e2e',
            APP_URL: baseURL,
            DB_DATABASE: database,
        },
    },
});
