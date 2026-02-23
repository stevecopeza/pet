import { test, expect } from '@playwright/test';

test.describe('Quote Blocks Adjustments', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/wp-login.php');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'stc54');
    await Promise.all([
      page.waitForNavigation(),
      page.click('#wp-submit'),
    ]);
  });

  test('section and quote totals reflect price adjustments and text blocks', async ({ page }) => {
    page.on('console', (msg) => console.log('BROWSER LOG:', msg.text()));

    await page.goto('/wp-admin/admin.php?page=pet-quotes-sales');

    await page.click('button:has-text("Quotes")');

    await page.click('button:has-text("Start building quote")');

    await page.waitForSelector('select');
    await page.selectOption('select', { index: 1 });

    await page.fill('input[placeholder="e.g. Q123 - Server Upgrade"]', 'Block Adjustments Test Quote');
    await page.fill('textarea', 'Quote for testing block adjustments and text blocks');

    await page.click('button:has-text("Start building quote")');

    await expect(page.locator('h2', { hasText: 'Quote #' })).toBeVisible({ timeout: 20000 });

    const totalValueLocator = page.locator('p', {
      hasText: 'Total Value:',
    });

    await expect(totalValueLocator).toBeVisible({ timeout: 10000 });

    const fabButton = page.getByRole('button', { name: 'Add Section' }).first();
    await expect(fabButton).toBeVisible({ timeout: 10000 });
    await fabButton.click();

    await expect(page.locator('text=New Section')).toBeVisible({ timeout: 10000 });

    const addBlockButton = page
      .getByRole('button', { name: '+ Add Block' })
      .first();
    await expect(addBlockButton).toBeVisible({ timeout: 10000 });
    await addBlockButton.click();

    await expect(page.locator('text=Select Block Type')).toBeVisible({ timeout: 10000 });

    await page.getByRole('button', { name: 'Once-off Simple Services' }).click();

    const serviceRow = page
      .locator('tr', { hasText: 'OnceOffSimpleServiceBlock' })
      .first();
    await expect(serviceRow).toBeVisible({ timeout: 10000 });

    const serviceEditor = page.locator('tr', { hasText: 'Quantity' }).first();

    await serviceEditor
      .locator('input[type="text"]')
      .first()
      .fill('Service Line');
    const serviceNumberInputs = serviceEditor.locator('input[type="number"]');
    await serviceNumberInputs.nth(0).fill('2');
    await serviceNumberInputs.nth(1).fill('');
    await expect(page.locator('h2', { hasText: 'Quote #' })).toBeVisible({
      timeout: 5000,
    });
    await serviceNumberInputs.nth(1).fill('100');

    await page.getByRole('button', { name: 'Save' }).click();

    await expect(totalValueLocator).toContainText('$200.00', { timeout: 10000 });
    await expect(page.locator('text=Section Total: $200.00')).toBeVisible({
      timeout: 10000,
    });

    await addBlockButton.click();

    await expect(page.locator('text=Select Block Type')).toBeVisible({ timeout: 10000 });

    await page.getByRole('button', { name: 'Price Adjustment' }).click();

    const adjustmentRow = page
      .locator('tr', { hasText: 'PriceAdjustmentBlock' })
      .first();
    await expect(adjustmentRow).toBeVisible({ timeout: 10000 });

    const adjustmentEditor = page.locator('tr', { hasText: 'Amount' }).first();

    await adjustmentEditor.locator('input[type="text"]').first().fill('Section uplift');
    await adjustmentEditor.locator('input[type="number"]').first().fill('50');

    await page.getByRole('button', { name: 'Save' }).click();

    await expect(page.locator('text=Section Total: $250.00')).toBeVisible({
      timeout: 10000,
    });
    await expect(totalValueLocator).toContainText('$250.00', { timeout: 10000 });

    await fabButton.click();

    const addBlockButtons = page.getByRole('button', { name: '+ Add Block' });
    await expect(addBlockButtons.nth(1)).toBeVisible({ timeout: 10000 });
    await addBlockButtons.nth(1).click();

    await expect(page.locator('text=Select Block Type')).toBeVisible({ timeout: 10000 });

    await page.getByRole('button', { name: 'Price Adjustment' }).click();

    const allAdjustmentRows = page.locator('tr', {
      hasText: 'PriceAdjustmentBlock',
    });
    await expect(allAdjustmentRows.nth(1)).toBeVisible({ timeout: 10000 });
    const quoteAdjustmentEditor = page.locator('tr', { hasText: 'Amount' }).nth(1);

    await quoteAdjustmentEditor.locator('input[type="text"]').first().fill('Quote uplift');
    await quoteAdjustmentEditor.locator('input[type="number"]').first().fill('30');

    await page.getByRole('button', { name: 'Save' }).click();

    await expect(totalValueLocator).toContainText('$280.00', { timeout: 10000 });
  });
});
