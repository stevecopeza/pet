import { test, expect } from '@playwright/test';

test.describe('System Demo Seed API', () => {
  let nonce: string;

  test.beforeEach(async ({ page }) => {
    await page.goto('/wp-login.php');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'stc54');
    await page.click('#wp-submit');
    await expect(page).toHaveTitle(/Dashboard|WordPress/);

    await page.goto('/wp-admin/index.php');
    const content = await page.content();
    const match = content.match(/"nonce":"([a-f0-9]+)"/);
    if (match) {
      nonce = match[1];
      console.log('Found nonce:', nonce);
    }
    expect(nonce).toBeTruthy();
  });

  test('seed_full returns 201 (or 422 with domain error) and purge works', async ({ page }) => {
    const seedRes = await page.request.post('/wp-json/pet/v1/system/demo/seed_full', {
      headers: { 'X-WP-Nonce': nonce },
    });

    const status = seedRes.status();
    console.log('Seed Full status:', status);

    // Accept either success or domain-controlled error
    expect([201, 422].includes(status)).toBeTruthy();

    const body = await seedRes.json();
    console.log('Seed Full response:', body);

    if (status === 201) {
      expect(body.seed_run_id).toBeTruthy();
      expect(typeof body.seed_run_id).toBe('string');
      expect(body.summary).toBeTruthy();

      // Purge using seed_run_id
      const purgeRes = await page.request.post('/wp-json/pet/v1/system/demo/purge', {
        headers: { 'X-WP-Nonce': nonce },
        data: { seed_run_id: body.seed_run_id },
      });
      console.log('Purge status:', purgeRes.status());
      expect(purgeRes.status()).toBe(200);
      const purgeBody = await purgeRes.json();
      expect(purgeBody.seed_run_id).toBe(body.seed_run_id);
      expect(purgeBody.summary).toBeTruthy();
    } else {
      // 422 domain_exception path
      expect(body.error).toBe('domain_exception');
      expect(body.message).toBeTruthy();
      expect(body.seed_run_id).toBeTruthy();
    }
  });
});
