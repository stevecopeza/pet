import { test, expect } from '@playwright/test';

test('Inline click-guard script is present on Support page', async ({ page }) => {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'stc54');
  await Promise.all([page.waitForNavigation(), page.click('#wp-submit')]);
  await expect(page.locator('#wpadminbar')).toBeVisible({ timeout: 10000 });
  await page.goto('/wp-admin/admin.php?page=pet-support');
  const html = await page.content();
  expect(html).toMatch(/pet-support button/);
  expect(html).toMatch(/href=\"#\"/);
});
