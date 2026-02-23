import { test, expect } from '@playwright/test';

test.describe('Quote Text Blocks', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/wp-login.php');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'stc54');
    await Promise.all([
      page.waitForNavigation(),
      page.click('#wp-submit'),
    ]);
  });

  test('TextBlock can be edited and shows collapsed preview', async ({ page }) => {
    page.on('console', (msg) => console.log('BROWSER LOG:', msg.text()));

    await page.goto('/wp-admin/admin.php?page=pet-quotes-sales');

    await page.click('button:has-text("Quotes")');

    await page.click('button:has-text("Start building quote")');

    await page.waitForSelector('select');
    await page.selectOption('select', { index: 1 });

    await page.fill(
      'input[placeholder="e.g. Q123 - Server Upgrade"]',
      'Text Block Test Quote'
    );
    await page.fill('textarea', 'Quote for testing text block editor');

    await page.click('button:has-text("Start building quote")');

    await expect(page.locator('h2', { hasText: 'Quote #' })).toBeVisible({
      timeout: 20000,
    });

    const addSectionFab = page.getByRole('button', { name: 'Add Section' }).first();
    await expect(addSectionFab).toBeVisible({ timeout: 10000 });
    await addSectionFab.click();

    const sectionCard = page.locator('div').filter({ hasText: 'New Section' }).first();
    await expect(sectionCard).toBeVisible({ timeout: 10000 });

    const addBlockButton = sectionCard.getByRole('button', { name: '+ Add Block' }).first();
    await expect(addBlockButton).toBeVisible({ timeout: 10000 });
    await addBlockButton.click();

    await expect(page.locator('text=Select Block Type')).toBeVisible({ timeout: 10000 });
    await page.getByRole('button', { name: 'Text Block' }).click();

    const textRow = page.locator('tr', { hasText: 'TextBlock' }).first();
    await expect(textRow).toBeVisible({ timeout: 20000 });

    const editorRow = page
      .locator('tr')
      .filter({ has: page.locator('textarea') })
      .first();
    await expect(editorRow).toBeVisible({ timeout: 10000 });

    const textarea = editorRow.locator('textarea').first();
    const textContent =
      'This is a text block note that should appear in the collapsed preview.';
    await textarea.fill(textContent);

    await page.getByRole('button', { name: 'Save' }).click();

    await expect(textRow.locator('td').nth(1)).toContainText(
      'This is a text block note',
      { timeout: 10000 }
    );
  });
});
