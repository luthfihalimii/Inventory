import { expect, test } from '@playwright/test';
import { addToCart, daysFromNow, login, loginAs } from './helpers';

test('pengajuan mendadak wajib alasan dan lolos batas H-2', async ({
    page,
}) => {
    await login(page, '2410000001');
    await addToCart(page, 'E2E Kamera');

    await page.selectOption('#category', 'urgent');
    await page.fill('#usage_location', 'Lab Jurnalistik');
    // Besok: melanggar aturan H-2 untuk pengajuan terjadwal, sah untuk mendadak.
    await page.fill('#pickup_at', daysFromNow(1));
    await page.fill('#return_at', daysFromNow(2));
    await page.check('input[type="checkbox"]');

    // Tanpa alasan: ditolak, tetap di form, tidak ada pengajuan baru.
    await page.getByRole('button', { name: 'Kirim pengajuan' }).click();
    await expect(page).toHaveURL(/\/peminjaman\/baru$/);
    await expect(page.getByText('E2E Kamera').first()).toBeVisible();

    await page.fill('#purpose', 'Liputan mendadak di kampus.');
    await page.getByRole('button', { name: 'Kirim pengajuan' }).click();

    await expect(page).toHaveURL(/\/peminjaman\/\d+$/);
    await expect(page.getByText('Menunggu persetujuan')).toBeVisible();

    const reference = (await page.locator('h1').innerText()).trim();
    expect(reference).toMatch(/^REQ-/);

    // Antrean approval menampilkan pengajuan mendadak beserta alasannya.
    await loginAs(page, 'korlab');
    await page.goto('/admin/approval');
    await page
        .locator('tr', { hasText: reference })
        .getByRole('link', { name: 'Periksa' })
        .click();

    await expect(page.locator('h1')).toHaveText(reference);
    await expect(page.getByText('Liputan mendadak di kampus.')).toBeVisible();
});
