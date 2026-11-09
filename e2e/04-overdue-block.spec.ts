import { expect, test } from '@playwright/test';
import { addToCart, daysFromNow, login, loginAs } from './helpers';

test('anggota overdue: blokir terlihat di form, submit ditolak, WM bisa memantau', async ({
    page,
}) => {
    await login(page, '2410000009');
    await addToCart(page, 'E2E Kamera');

    // Form sudah menjelaskan alasan blokir beserta transaksi yang menahan.
    await expect(
        page.getByText('Peminjaman diblokir karena pinjaman terlambat.'),
    ).toBeVisible();
    await expect(page.getByText('REQ-E2E-000009')).toBeVisible();

    await page.selectOption('#category', 'scheduled');
    await page.fill('#usage_location', 'Lab Jurnalistik');
    await page.fill('#pickup_at', daysFromNow(3));
    await page.fill('#return_at', daysFromNow(5));
    await page.check('input[type="checkbox"]');

    // Backend menolak walau form dikirim: tidak ada pengajuan baru.
    await page.getByRole('button', { name: 'Kirim pengajuan' }).click();
    await expect(page).toHaveURL(/\/peminjaman\/baru$/);
    await expect(
        page.getByText('Anggota sedang diblokir karena pinjaman terlambat.'),
    ).toBeVisible();

    await page.goto('/peminjaman');
    await expect(page.locator('tbody tr')).toHaveCount(1);
    await expect(page.getByText('REQ-E2E-000009')).toBeVisible();

    // WM memantau daftar anggota terblokir.
    await loginAs(page, 'wm');
    await page.goto('/wm/blocked');
    await expect(page.getByText('Anggota Terlambat E2E')).toBeVisible();
    await expect(page.getByText('REQ-E2E-000009')).toBeVisible();
});
