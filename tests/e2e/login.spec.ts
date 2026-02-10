import { test, expect } from '@playwright/test';

test('has title and can login', async ({ page }) => {
  await page.goto('/wp-login.php');

  // Expect a title "to contain" a substring.
  await expect(page).toHaveTitle(/Log In/);

  // Fill login form
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'stc54');
  
  // Click login
  await page.click('#wp-submit');

  // Verify dashboard access
  await expect(page).toHaveTitle(/Dashboard/);
  await expect(page.locator('#wpadminbar')).toBeVisible();
});
