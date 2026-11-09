import { expect, test } from '@playwright/test';
import { login } from './helpers';

test('pengembalian rusak membuat laporan kerusakan dan stok tertahan', async ({
    page,
}) => {
    await login(page, 'korlab');

    await page.goto('/admin/returns');
    await page
        .locator('tr', { hasText: 'REQ-E2E-000006' })
        .getByRole('link', { name: 'Periksa' })
        .click();

    // Satu baris inspeksi: ubah kondisi menjadi rusak.
    await page.locator('select').nth(1).selectOption('damaged');
    await page.fill('#notes', 'Lensa tergores saat inspeksi E2E.');
    await page.getByRole('button', { name: 'Selesaikan transaksi' }).click();

    await expect(page).toHaveURL(/\/admin\/returns$/);

    // Laporan kerusakan terbuka, dan barang tidak bisa dipinjam lagi.
    await page.goto('/admin/inventory/damage-reports');
    const report = page.locator('tr', { hasText: 'E2E Kamera' }).first();
    await expect(report).toBeVisible();
    await expect(report.getByText('Rusak')).toBeVisible();
    await expect(report.getByText('Terbuka')).toBeVisible();
    await expect(
        report.getByText('E2E Kamera', { exact: false }),
    ).toBeVisible();
});
