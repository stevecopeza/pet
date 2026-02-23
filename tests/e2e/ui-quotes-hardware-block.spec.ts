import { test, expect } from '@playwright/test';

test.describe('Quote Hardware Blocks', () => {
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

  test('section and quote totals reflect hardware block quantity and unit price', async ({ page }) => {
    page.on('console', (msg) => console.log('BROWSER LOG:', msg.text()));

    await page.goto('/wp-admin/admin.php?page=pet-quotes-sales');
    await expect(page.locator('h1', { hasText: 'PET - Quotes & Sales' })).toBeVisible();

    await page.click('button:has-text("Quotes")');

    await page.click('button:has-text("Start building quote")');

    await page.waitForSelector('select');
    await page.selectOption('select', { index: 1 });

    await page.fill('input[placeholder="e.g. Q123 - Server Upgrade"]', 'Hardware Block Test Quote');
    await page.fill('textarea', 'Quote for testing once-off hardware block editor');

    await page.click('button:has-text("Start building quote")');

    await expect(page.locator('h2', { hasText: 'Quote #' })).toBeVisible({ timeout: 20000 });

    const totalValueLocator = page.locator('text=Total Value:');
    await expect(totalValueLocator).toBeVisible({ timeout: 10000 });

    const addSectionFab = page.getByRole('button', { name: 'Add Section' }).first();
    await expect(addSectionFab).toBeVisible({ timeout: 10000 });
    await addSectionFab.click();

    const firstSection = page.locator('div').filter({ hasText: 'New Section' }).first();
    await expect(firstSection).toBeVisible({ timeout: 10000 });

    const firstSectionAddBlock = firstSection.getByRole('button', { name: '+ Add Block' }).first();
    await expect(firstSectionAddBlock).toBeVisible({ timeout: 10000 });
    await firstSectionAddBlock.click();

    await expect(page.locator('text=Select Block Type')).toBeVisible({ timeout: 10000 });

    await page.getByRole('button', { name: 'Once-off Hardware' }).click();

    const hardwareRow = firstSection.locator('tr', { hasText: 'HardwareBlock' }).first();
    await expect(hardwareRow).toBeVisible({ timeout: 10000 });

    await hardwareRow.getByRole('button', { name: 'Edit' }).click();

    const editorRow = page
      .locator('tr')
      .filter({ hasText: 'Catalog Item' })
      .filter({ hasText: 'Quantity' })
      .filter({ hasText: 'Unit Price' })
      .first();
    await expect(editorRow).toBeVisible({ timeout: 10000 });

    const quantityInput = editorRow.locator('input[type="number"]').nth(0);
    const unitPriceInput = editorRow.locator('input[type="number"]').nth(1);

    await quantityInput.fill('3');
    await unitPriceInput.fill('150');

    await page.getByRole('button', { name: 'Save' }).click();

    await expect(totalValueLocator).toContainText('$450.00', { timeout: 10000 });
    await expect(page.locator('text=Section Total: $450.00')).toBeVisible({
      timeout: 10000,
    });
  });
});

