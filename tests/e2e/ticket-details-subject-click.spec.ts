import { test, expect } from '@playwright/test';

test.describe('Ticket Details via Subject Click', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/wp-login.php');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'stc54');
    await Promise.all([page.waitForNavigation(), page.click('#wp-submit')]);
    await expect(page.locator('#wpadminbar')).toBeVisible({ timeout: 10000 });
  });

  test('details stay visible after clicking subject', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=pet-support');
    await expect(page.getByRole('heading', { name: 'Support (Tickets)' })).toBeVisible();

    // Wait for at least one data row
    await expect(page.locator('table.wp-list-table tbody tr').first()).toBeVisible();

    // Click the first subject button-link in the table
    const firstSubject = page.locator('table.wp-list-table tbody tr td .button-link').first();
    await firstSubject.click();

    // Expect back button in details view
    const backButton = page.getByRole('button', { name: /Back to Tickets/ });
    await expect(backButton).toBeVisible({ timeout: 5000 });

    await page.waitForTimeout(1500);
    await expect(backButton).toBeVisible();
  });
});
