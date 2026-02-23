import { test, expect } from '@playwright/test';

test.describe('Quote Sections', () => {
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

  test('can add a new section from the quote details FAB', async ({ page }) => {
    page.on('console', msg => console.log('BROWSER LOG:', msg.text()));

    await page.goto('/wp-admin/admin.php?page=pet-quotes-sales');
    await expect(page.locator('h1', { hasText: 'PET - Quotes & Sales' })).toBeVisible();

    await page.click('button:has-text("Quotes")');

    await page.click('button:has-text("Start building quote")');

    await page.waitForSelector('select');
    await page.selectOption('select', { index: 1 });

    await page.fill('input[placeholder="e.g. Q123 - Server Upgrade"]', 'Section Test Quote');
    await page.fill('textarea', 'Quote for testing sections');

    await page.click('button:has-text("Start building quote")');

    await expect(page.locator('h2', { hasText: 'Quote #' })).toBeVisible({ timeout: 20000 });

    const fabButton = page.getByRole('button', { name: 'Add Section' }).first();
    await expect(fabButton).toBeVisible({ timeout: 10000 });
    await fabButton.click();

    await expect(page.locator('text=New Section')).toBeVisible({ timeout: 10000 });
  });

  test('can add a block into a section', async ({ page }) => {
    page.on('console', msg => console.log('BROWSER LOG:', msg.text()));

    await page.goto('/wp-admin/admin.php?page=pet-quotes-sales');
    await expect(page.locator('h1', { hasText: 'PET - Quotes & Sales' })).toBeVisible();

    await page.click('button:has-text("Quotes")');

    await page.click('button:has-text("Start building quote")');

    await page.waitForSelector('select');
    await page.selectOption('select', { index: 1 });

    await page.fill('input[placeholder="e.g. Q123 - Server Upgrade"]', 'Section Block Test Quote');
    await page.fill('textarea', 'Quote for testing section blocks');

    await page.click('button:has-text("Start building quote")');

    await expect(page.locator('h2', { hasText: 'Quote #' })).toBeVisible({ timeout: 20000 });

    const fabButton = page.getByRole('button', { name: 'Add Section' }).first();
    await expect(fabButton).toBeVisible({ timeout: 10000 });
    await fabButton.click();

    await expect(page.locator('text=New Section')).toBeVisible({ timeout: 10000 });

    const sectionCard = page.locator('div').filter({ hasText: 'New Section' }).first();
    const addBlockButton = sectionCard.getByRole('button', { name: '+ Add Block' });
    await expect(addBlockButton).toBeVisible({ timeout: 10000 });
    await addBlockButton.click();

    await expect(page.locator('text=Select Block Type')).toBeVisible({ timeout: 10000 });

    await page.getByRole('button', { name: 'Once-off Hardware' }).click();

    await expect(
      sectionCard.locator('td', { hasText: 'HardwareBlock' }).first()
    ).toBeVisible({ timeout: 10000 });
  });
});
