import { expect, test } from '@playwright/test';
import { addToCart, daysFromNow, login, loginAs } from './helpers';

test('alur kritis: anggota mengajukan, admin menyetujui, serah terima, lalu verifikasi kembali', async ({
    page,
}) => {
    await login(page, '2410000001');

    // Anggota: barang masuk keranjang lalu diajukan.
    await addToCart(page, 'E2E Kamera');
    await page.selectOption('#category', 'scheduled');
    await page.fill('#usage_location', 'Lab Jurnalistik');
    await page.fill('#pickup_at', daysFromNow(3));
    await page.fill('#return_at', daysFromNow(5));
    await page.check('input[type="checkbox"]');
    await page.getByRole('button', { name: 'Kirim pengajuan' }).click();

    await expect(page).toHaveURL(/\/peminjaman\/\d+$/);
    await expect(page.getByText('Menunggu persetujuan')).toBeVisible();

    const reference = (await page.locator('h1').innerText()).trim();
    expect(reference).toMatch(/^REQ-/);

    // Admin: setujui pengajuan.
    await loginAs(page, 'korlab');
    await page.goto('/admin/approval');
    await page
        .locator('tr', { hasText: reference })
        .getByRole('link', { name: 'Periksa' })
        .click();
    await expect(page.locator('h1')).toHaveText(reference);

    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Setujui', exact: true }).click();
    await expect(page).toHaveURL(/\/admin\/approval$/);

    // Admin: serah terima. Filter lewat query agar transaksi lain di DB E2E tidak terambil.
    await page.goto(`/admin/checkout?q=${encodeURIComponent(reference)}`);
    await expect(page.locator('tbody tr')).toHaveCount(1);
    await page.getByRole('link', { name: 'Serahkan' }).click();
    await expect(page.locator('h1')).toHaveText(reference);

    // Scan unit qty: token barang, bukan token unit.
    await page.fill('#scan_token', 'e2e-kamera-token');
    await page.getByRole('button', { name: 'Periksa' }).click();
    await expect(page.getByText('E2E Kamera cocok.')).toBeVisible();

    await page.getByRole('button', { name: 'Konfirmasi serah terima' }).click();
    await expect(page).toHaveURL(/\/admin\/checkout$/);

    // Anggota: ajukan pengembalian.
    await loginAs(page, '2410000001');
    await page.goto('/peminjaman');
    await page
        .locator('tr', { hasText: reference })
        .getByRole('link', { name: 'Detail' })
        .click();
    await expect(page.getByText('Dipinjam')).toBeVisible();

    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Ajukan pengembalian' }).click();
    await expect(
        page.getByText('Menunggu verifikasi pengembalian'),
    ).toBeVisible();

    // Admin: verifikasi kembali dengan kondisi baik.
    await loginAs(page, 'korlab');
    await page.goto('/admin/returns');
    await page
        .locator('tr', { hasText: reference })
        .getByRole('link', { name: 'Periksa' })
        .click();
    await page.getByRole('button', { name: 'Selesaikan transaksi' }).click();
    await expect(page).toHaveURL(/\/admin\/returns$/);

    // Anggota: transaksi selesai, tidak ada tombol pengembalian lagi.
    await loginAs(page, '2410000001');
    await page.goto('/peminjaman');
    await page
        .locator('tr', { hasText: reference })
        .getByRole('link', { name: 'Detail' })
        .click();
    await expect(page.getByText('Status pinjaman:')).toBeVisible();
    await expect(page.getByText('Selesai')).toBeVisible();
    await expect(
        page.getByRole('button', { name: 'Ajukan pengembalian' }),
    ).toHaveCount(0);
});
