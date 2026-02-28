import { test, expect } from '@playwright/test';

test.describe('Archive Ticket Smoke', () => {
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

  test('archive the UI Seeded Demo Ticket', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=pet-support');
    await expect(page.getByRole('heading', { name: 'Support (Tickets)' })).toBeVisible();

    const ticketRow = page.getByText('UI Seeded Demo Ticket', { exact: true });
    await expect(ticketRow).toBeVisible({ timeout: 10000 });

    page.once('dialog', async (dialog) => {
      await dialog.accept();
    });

    await ticketRow.locator('xpath=ancestor::tr').getByRole('button', { name: 'Archive' }).click();

    await expect(page.getByText('UI Seeded Demo Ticket', { exact: true })).toHaveCount(0, { timeout: 10000 });
  });
});
