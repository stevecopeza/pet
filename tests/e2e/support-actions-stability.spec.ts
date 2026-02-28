import { test, expect } from '@playwright/test';

test.describe('Support Actions Stability', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/wp-login.php');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'stc54');
    await Promise.all([page.waitForNavigation(), page.click('#wp-submit')]);
    await expect(page.locator('#wpadminbar')).toBeVisible({ timeout: 10000 });
  });

  test('View keeps details visible', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=pet-support');
    await expect(page.getByRole('heading', { name: 'Support (Tickets)' })).toBeVisible();
    const firstRow = page.locator('table.wp-list-table tbody tr').first();
    await expect(firstRow).toBeVisible();
    await firstRow.getByRole('button', { name: 'View', exact: true }).click();
    // Hash should be set by inline guard (fallback)
    const hash = await page.evaluate(() => window.location.hash);
    expect(hash).toMatch(/#ticket=\d+/);
    await expect(page.getByRole('heading', { name: 'Description' })).toBeVisible({ timeout: 5000 });
    await page.waitForTimeout(1500);
    await expect(page.getByRole('heading', { name: 'Description' })).toBeVisible();
  });

  test('Edit opens ticket form and stays open', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=pet-support');
    await expect(page.getByRole('heading', { name: 'Support (Tickets)' })).toBeVisible();
    const firstRow = page.locator('table.wp-list-table tbody tr').first();
    await expect(firstRow).toBeVisible();
    await firstRow.getByRole('button', { name: 'Edit', exact: true }).click();
    const hash = await page.evaluate(() => window.location.hash);
    expect(hash).toMatch(/#ticket=\d+/);
    await expect(page.locator('label:has-text("Subject:")')).toBeVisible({ timeout: 5000 });
    await expect(page.locator('label:has-text("Description:")')).toBeVisible();
    await page.waitForTimeout(1500);
    await expect(page.locator('label:has-text("Subject:")')).toBeVisible();
  });
});
