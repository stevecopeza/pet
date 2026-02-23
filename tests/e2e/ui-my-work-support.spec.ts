import { test, expect } from '@playwright/test';

test.describe('My Work - Support Tickets', () => {
  test('shows tickets assigned to current user', async ({ page }) => {
    // Log in as steve (same credentials you provided)
    await page.goto('/wp-login.php');
    await page.fill('#user_login', 'steve');
    await page.fill('#user_pass', 'stc54');
    await Promise.all([
      page.waitForNavigation(),
      page.click('#wp-submit'),
    ]);
    await expect(page.locator('#wpadminbar')).toBeVisible({ timeout: 10000 });

    // Go to My Work page that renders the shortcode
    await page.goto('/shortcodes/my-work/');

    // Ensure the My Work container is visible
    const myWork = page.locator('.pet-my-work');
    await expect(myWork).toBeVisible({ timeout: 10000 });

    // Look for at least one Support Tickets row (table body) if any tickets are assigned
    const supportSection = myWork.locator('h3:has-text("Support Tickets")');
    await expect(supportSection).toBeVisible();

    const supportTableRows = myWork.locator('.pet-my-work-section >> text=Support Tickets')
      .locator('xpath=ancestor::div[contains(@class,"pet-my-work-section")]')
      .locator('tbody tr');

    // If there are no rows, this will fail with a clearer message
    const rowCount = await supportTableRows.count();
    expect(rowCount).toBeGreaterThan(0);
  });
});
