import { expect, test } from '@playwright/test';
import { login } from './helpers';

test('QR salah ditolak dan tidak mengubah status transaksi', async ({
    page,
}) => {
    await login(page, 'korlab');

    await page.goto('/admin/checkout');
    await page.fill(
        'input[placeholder="Cari nomor referensi"]',
        'REQ-E2E-000001',
    );
    await page.getByRole('button', { name: 'Cari' }).click();
    await page.getByRole('link', { name: 'Serahkan' }).first().click();

    // Token tidak dikenal.
    await page.fill('#scan_token', 'token-tidak-ada');
    await page.getByRole('button', { name: 'Periksa' }).click();
    await expect(page.getByText('Token tidak dikenali.')).toBeVisible();

    // Token sah, tapi barangnya bukan bagian dari pengajuan ini.
    await page.fill('#scan_token', 'e2e-kamera-token');
    await page.getByRole('button', { name: 'Periksa' }).click();
    await expect(
        page.getByText('Barang bukan bagian dari pengajuan ini.'),
    ).toBeVisible();

    // Status tidak berubah: transaksi masih menunggu penyerahan.
    await page.goto('/admin/checkout');
    await expect(
        page.getByRole('cell', { name: 'REQ-E2E-000001', exact: true }),
    ).toBeVisible();

    // Token unit yang benar diterima tanpa menyentuh database.
    await page.getByRole('link', { name: 'Serahkan' }).first().click();
    await page.fill('#scan_token', 'e2e-tripod-asset-1');
    await page.getByRole('button', { name: 'Periksa' }).click();
    await expect(page.getByText('E2E-AST-1 cocok.')).toBeVisible();

    await page.goto('/admin/checkout');
    await expect(
        page.getByRole('cell', { name: 'REQ-E2E-000001', exact: true }),
    ).toBeVisible();
});
