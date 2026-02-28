import { test, expect } from '@playwright/test';

test.describe('Ticket Details Stability', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/wp-login.php');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'stc54');
    await Promise.all([page.waitForNavigation(), page.click('#wp-submit')]);
    await expect(page.locator('#wpadminbar')).toBeVisible({ timeout: 10000 });
  });

  test('details stay visible after clicking a ticket', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=pet-support');
    await expect(page.getByRole('heading', { name: 'Support (Tickets)' })).toBeVisible();

    // Ensure first data row is available
    const firstRow = page.locator('table.wp-list-table tbody tr').first();
    await expect(firstRow).toBeVisible();
    await firstRow.getByRole('button', { name: 'View', exact: true }).click();

    // Expect a details-only element
    await expect(page.getByRole('heading', { name: 'Description' })).toBeVisible({ timeout: 5000 });

    // Wait briefly and ensure still visible (no flicker back to list)
    await page.waitForTimeout(1500);
    await expect(page.getByRole('heading', { name: 'Description' })).toBeVisible();
  });
});
