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

    await expect(page.locator('h1:has-text("Support (Tickets)")')).toBeVisible();

    await expect(page.locator('label:has-text("Category:")')).toBeVisible();
    await expect(page.locator('label:has-text("Subcategory:")')).toBeVisible();
    await expect(page.locator('label:has-text("Source:")')).toBeVisible();
    await expect(page.locator('label:has-text("Contact (Optional):")')).toBeVisible();
    await expect(page.locator('label:has-text("Assignment:")')).toBeVisible();
  });
});

