import { test, expect } from '@playwright/test';

test.describe('Quotes Save Draft and Listing', () => {
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

  test('saving a draft quote returns to list and shows in Quotes table', async ({ page }) => {
    page.on('console', msg => console.log('BROWSER LOG:', msg.text()));

    await page.goto('/wp-admin/admin.php?page=pet-quotes-sales');
    await expect(page.locator('h1', { hasText: 'PET - Quotes & Sales' })).toBeVisible();

    await page.click('button:has-text("Quotes")');

    await page.click('button:has-text("Start building quote")');

    await page.waitForSelector('select');
    await page.selectOption('select', { index: 1 });

    await page.fill('input[placeholder="e.g. Q123 - Server Upgrade"]', 'Save Draft List Quote');
    await page.fill('textarea', 'Quote created to verify Save Draft returns to list');

    await page.click('button:has-text("Start building quote")');

    await expect(page.locator('h2', { hasText: 'Quote #' })).toBeVisible({ timeout: 20000 });

    await page.click('button:has-text("Save Draft")');

    await page.click('button:has-text("Quotes")');

    await page.waitForSelector('table tbody tr');

    const rows = await page.locator('table tbody tr').count();
    expect(rows).toBeGreaterThan(0);

    await expect(page.locator('table')).toContainText('draft');
  });
});
