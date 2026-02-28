import { test, expect } from '@playwright/test';

test.describe('Support Ticket Form', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/wp-login.php');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'stc54');
    await Promise.all([
      page.waitForNavigation(),
      page.click('#wp-submit'),
    ]);
    await expect(page.locator('#wpadminbar')).toBeVisible({ timeout: 10000 });
  });

  test('shows category, subcategory, source, contact and assignment fields', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=pet-support');

    await expect(page.getByRole('heading', { name: 'Support (Tickets)' })).toBeVisible();

    // Open the create ticket form
    await page.getByRole('button', { name: 'Create New Ticket' }).click();

    await expect(page.getByText('Category:', { exact: true })).toBeVisible();
    await expect(page.getByText('Subcategory:', { exact: true })).toBeVisible();
    await expect(page.getByText('Source:', { exact: true })).toBeVisible();
    await expect(page.getByText('Contact (Optional):', { exact: true })).toBeVisible();
    await expect(page.getByText('Assignment:', { exact: true })).toBeVisible();
  });
});
