import { expect, test } from '@playwright/test';
import { login, loginAs } from './helpers';

test.describe('UI shell', () => {
    test('login fits mobile and hides the showcase', async ({ page }) => {
        await page.setViewportSize({ width: 375, height: 812 });
        await page.goto('/login');
        await expect(page.getByRole('button', { name: 'Masuk' })).toBeVisible();
        await expect(
            page.getByText('Inventaris rapi, peminjaman tercatat.'),
        ).toBeHidden();
        const overflow = await page.evaluate(
            () => document.documentElement.scrollWidth > window.innerWidth,
        );
        expect(overflow).toBe(false);
    });

    test('dashboard holds at desktop and mobile', async ({ page }) => {
        await page.setViewportSize({ width: 1440, height: 900 });
        await login(page, 'korlab');

        await expect(page.locator('[data-sidebar="sidebar"]')).toBeVisible();
        await expect(
            page.getByRole('heading', { name: 'Dashboard', exact: true }),
        ).toBeVisible();
        await expect(
            page.getByText('Menunggu approval', { exact: true }),
        ).toBeVisible();

        const chart = page.locator('figure').filter({
            has: page.getByRole('heading', {
                name: 'Pengajuan peminjaman, 6 bulan terakhir',
                exact: true,
            }),
        });
        await expect(chart).toBeVisible();
        await expect(chart.locator('svg')).toBeVisible();
        await expect(
            chart.getByRole('table', {
                name: 'Data Pengajuan peminjaman, 6 bulan terakhir',
            }),
        ).toBeAttached();

        await page.setViewportSize({ width: 375, height: 812 });
        const menu = page.getByRole('button', { name: 'Buka menu navigasi' });
        await expect(menu).toBeVisible();
        await expect(menu).toContainText('Menu');

        const fitsViewport = await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        );
        expect(fitsViewport).toBe(true);
    });

    test('catalog, borrowing, and admin queues fit narrow mobile widths', async ({
        page,
    }) => {
        await login(page, '2410000001');

        for (const width of [320, 375]) {
            await page.setViewportSize({ width, height: 812 });

            for (const path of ['/inventory', '/peminjaman']) {
                await page.goto(path);
                await expect(page.locator('main#main-content')).toBeVisible();
                expect(
                    await page.evaluate(
                        () =>
                            document.documentElement.scrollWidth <=
                            window.innerWidth,
                    ),
                ).toBe(true);
            }
        }

        await loginAs(page, 'korlab');

        for (const width of [320, 375]) {
            await page.setViewportSize({ width, height: 812 });
            await page.goto('/admin/approval');
            await expect(page.locator('main#main-content')).toBeVisible();
            expect(
                await page.evaluate(
                    () =>
                        document.documentElement.scrollWidth <=
                        window.innerWidth,
                ),
            ).toBe(true);
        }
    });

    test('staff navigation hides member borrowing and WM gets admin tools', async ({
        page,
    }) => {
        await login(page, 'korlab');

        await expect(
            page.getByRole('link', { name: 'Peminjaman Saya' }),
        ).toHaveCount(0);
        await expect(
            page.getByRole('link', { name: 'Kelola barang' }),
        ).toBeVisible();
        await expect(
            page.getByRole('link', { name: 'Anggota', exact: true }),
        ).toHaveCount(0);

        await loginAs(page, 'wm');

        await expect(
            page.getByRole('link', { name: 'Peminjaman Saya' }),
        ).toHaveCount(0);
        await expect(
            page.getByRole('link', { name: 'Kelola barang' }),
        ).toBeVisible();
        await expect(
            page.getByRole('link', { name: 'Anggota', exact: true }),
        ).toBeVisible();
    });
});
