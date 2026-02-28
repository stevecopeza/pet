import { test, expect } from '@playwright/test';

test.describe('Create Ticket Smoke', () => {
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

  test('create a simple demo ticket via UI', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=pet-support');
    await expect(page.getByRole('heading', { name: 'Support (Tickets)' })).toBeVisible();

    await page.getByRole('button', { name: 'Create New Ticket' }).click();

    const customerSelect = page.locator('label:has-text("Customer:") + select');
    await customerSelect.waitFor();
    const customerOptions = customerSelect.locator('option');
    const optionCount = await customerOptions.count();
    if (optionCount > 1) {
      await customerSelect.selectOption({ index: 1 });
    } else {
      await customerSelect.selectOption({ index: 0 });
    }

    await page.locator('label:has-text("Subject:") + input').fill('UI Seeded Demo Ticket');
    await page.locator('label:has-text("Source:") + select').selectOption('portal');
    await page.locator('label:has-text("Description:") + textarea').fill('Seeded via Playwright smoke test');

    await Promise.all([
      page.waitForResponse((resp) => resp.url().includes('/wp-json/pet/v1/tickets') && [200,201].includes(resp.status())),
      page.getByRole('button', { name: 'Create Ticket' }).click(),
    ]);

    await expect(page.getByText('UI Seeded Demo Ticket', { exact: true })).toBeVisible({ timeout: 10000 });
  });
});
