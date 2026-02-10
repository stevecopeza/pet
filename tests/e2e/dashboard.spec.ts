import { test, expect } from '@playwright/test';

test('pet dashboard loads and fetches data', async ({ page }) => {
  // 1. Login
  await page.goto('/wp-login.php');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'stc54');
  await page.click('#wp-submit');
  await expect(page.locator('#wpadminbar')).toBeVisible();

  // 2. Navigate to PET Dashboard
  await page.goto('/wp-admin/admin.php?page=pet-dashboard');

  // 3. Verify React App Mount
  await expect(page.locator('h1', { hasText: 'PET - Overview' })).toBeVisible();

  // 4. Verify Data Loading
  // Wait for the overview stats to appear
  await expect(page.locator('.pet-card.overview')).toBeVisible();
  
  // Check for specific data points (mock data)
  const activeProjectsBox = page.locator('.stat-box').filter({ hasText: 'Active Projects' });
  await expect(activeProjectsBox).toBeVisible();
  await expect(activeProjectsBox.locator('.stat-value')).toHaveText('12');

  const revenueBox = page.locator('.stat-box').filter({ hasText: 'Revenue (MTD)' });
  await expect(revenueBox).toBeVisible();
  await expect(revenueBox.locator('.stat-value')).toHaveText('$125,000');
  
  // Check for recent activity
  await expect(page.locator('.pet-card.activity')).toBeVisible();
  // Now it's a table, so we check for td/tr or specific text
  await expect(page.locator('table')).toBeVisible();
  await expect(page.locator('td', { hasText: 'Quote Q-2024-001 accepted by Acme Corp' })).toBeVisible();
});
