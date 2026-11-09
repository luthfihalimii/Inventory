import { expect, type Page } from '@playwright/test';

export const password = 'password';

export async function login(page: Page, username: string): Promise<void> {
    await page.goto('/login');
    await page.fill('#username', username);
    await page.fill('#password', password);
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/\/dashboard/);
}

/** Local datetime-local value N days from today. */
export function daysFromNow(days: number): string {
    const date = new Date();
    date.setDate(date.getDate() + days);
    const pad = (value: number) => String(value).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T10:00`;
}

/** Switch account without leaving the browser context (cart stays put). */
export async function loginAs(page: Page, username: string): Promise<void> {
    await page.context().clearCookies();
    await login(page, username);
}

export async function addToCart(page: Page, itemName: string): Promise<void> {
    await page.goto('/inventory');
    await page.getByRole('link', { name: itemName }).click();
    await page.getByRole('button', { name: 'Tambah ke keranjang' }).click();
    await page.getByRole('link', { name: /Keranjang/ }).click();
    await expect(page).toHaveURL(/\/peminjaman\/baru/);
}
