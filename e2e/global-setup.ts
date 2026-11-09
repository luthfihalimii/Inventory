import { execSync } from 'node:child_process';

const database = process.env.E2E_DB || 'ent_inventory_e2e';
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
    run('php e2e/create-database.php');
    run('php artisan migrate:fresh --seed --force');
    run('php artisan db:seed --class=E2eSeeder --force');
}
