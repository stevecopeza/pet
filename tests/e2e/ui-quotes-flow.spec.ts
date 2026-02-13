import { test, expect } from '@playwright/test';

test.describe('UI Quotes Flow', () => {
  test.beforeEach(async ({ page }) => {
    // Login
    await page.goto('/wp-login.php');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'stc54');
    await page.click('#wp-submit');
    await expect(page.locator('#wpadminbar')).toBeVisible();
  });

  test('create quote and verify auto-navigation to details with line items', async ({ page }) => {
  page.on('console', msg => console.log('BROWSER LOG:', msg.text()));

  // 1. Navigate to Quotes & Sales
    await page.goto('/wp-admin/admin.php?page=pet-quotes-sales');
    await expect(page.locator('h1', { hasText: 'PET - Quotes & Sales' })).toBeVisible();

    // Switch to Quotes tab
    await page.click('button:has-text("Quotes")');

    // 2. Click Start building quote
    await page.click('button:has-text("Start building quote")');
    
    // 3. Fill Form
    // Wait for customer select to populate
    await page.waitForSelector('select');
    await page.selectOption('select', { index: 1 }); // Select first available customer
    
    // Fill Title and Description
    await page.fill('input[placeholder="e.g. Q123 - Server Upgrade"]', 'E2E Test Quote');
    await page.fill('textarea', 'This is an automated test quote');

    // 4. Submit
    await page.click('button:has-text("Start building quote")');

    // 5. Verify Auto-Navigation to Details
    try {
      await expect(page.locator('button:has-text("Add Component")')).toBeVisible({ timeout: 10000 });
    } catch (e) {
       console.log('Timeout waiting for Add Component button. Body text:');
       console.log(await page.innerText('body'));
       throw e;
     }

    // 6. Add a Component
    await page.click('button:has-text("Add Component")');
    await page.click('button:has-text("Once-off Product")');
    
    // Wait for modal to appear
    await expect(page.locator('.card', { hasText: 'Add Once-off Product' })).toBeVisible();

    // Fill form using IDs
    await page.selectOption('#component-select', { index: 1 });
    await page.fill('#component-description', 'Test Product Item'); 
    await page.fill('#component-quantity', '2');
    await page.fill('#component-price', '100');
    
    // Click Add Component (submit button in the form)
    await page.click('button:has-text("Add Component")');


    // 7. Verify Line Item Added
    // Wait for table to update
    try {
      // Check for the text in any element first to see if it's rendered at all
      await expect(page.locator('text=Test Product Item')).toBeVisible({ timeout: 15000 });
    } catch (e) {
      console.log('Timeout waiting for Line Item text. Body text:');
      console.log(await page.innerText('body'));
      throw e;
    }

    // 9. Send Quote
    page.on('dialog', dialog => dialog.accept());
    await page.click('button:has-text("Send Quote")');
    // Wait for state change
    await expect(page.locator('.pet-status-badge')).toHaveText('sent', { timeout: 10000 });

    // 10. Accept Quote
    await page.click('button:has-text("Accept Quote")');
    // Wait for state change to accepted
    await expect(page.locator('.pet-status-badge')).toHaveText('accepted', { timeout: 10000 });
  });
});
