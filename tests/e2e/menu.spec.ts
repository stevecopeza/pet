import { test, expect } from '@playwright/test';

test('pet menu structure and routing works', async ({ page }) => {
  // 1. Login
  await page.goto('/wp-login.php');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'stc54');
  await page.click('#wp-submit');
  await expect(page.locator('#wpadminbar')).toBeVisible();

  // 2. Check Top Level Menu
  const petMenu = page.locator('#toplevel_page_pet-dashboard');
  await expect(petMenu).toBeVisible();
  await expect(petMenu).toContainText('PET');

  // 3. Hover to see submenus
  await petMenu.hover();
  
  // 4. Verify Submenu Items
  const submenus = [
    'Overview',
    'Dashboards',
    'Customers',
    'Quotes & Sales',
    'Delivery',
    'Time',
    'Support',
    'Knowledge',
    'Staff',
    'Activity',
    'Settings'
  ];

  for (const item of submenus) {
    await expect(page.locator('.wp-submenu a', { hasText: item })).toBeVisible();
  }

  // 5. Navigate to a submenu (e.g., Customers)
  await page.click('.wp-submenu a:has-text("Customers")');
  await expect(page.locator('h1', { hasText: 'PET - Customers' })).toBeVisible();
  // Expect Customers component content instead of Coming Soon
  await expect(page.locator('.pet-customers h2', { hasText: 'Customers' })).toBeVisible();

  // 7. Navigate back to Overview
  await page.click('.wp-submenu a:has-text("Overview")');
  await expect(page).toHaveURL(/page=pet-dashboard/);
  await expect(page.locator('h1', { hasText: 'PET - Overview' })).toBeVisible();
  await expect(page.locator('.pet-card.overview')).toBeVisible(); // Check if Dashboard component loaded
});
