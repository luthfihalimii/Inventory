import { execSync } from 'node:child_process';

const database = process.env.E2E_DB || 'ent_inventory_e2e';

// `migrate:fresh` wipes the database it resolves to: never let that be dev or test.
// A cached config (bootstrap/cache/config.php) would override the env below.
if (!database.endsWith('_e2e')) {
    throw new Error(
        `Refusing to run E2E against "${database}": database name must end with "_e2e".`,
    );
}

const env = {
    ...process.env,
    APP_ENV: 'e2e',
    DB_DATABASE: database,
    APP_URL: 'http://127.0.0.1:8123',
};

function run(command: string): void {
    execSync(command, { cwd: process.cwd(), env, stdio: 'inherit' });
}

export default function globalSetup(): void {
    // A cached config would win over the env vars below and could point migrate:fresh
    // at the dev database.
    run('php artisan config:clear');
    run('php e2e/create-database.php');
    run('php artisan migrate:fresh --seed --force');
    run('php artisan db:seed --class=E2eSeeder --force');
}
