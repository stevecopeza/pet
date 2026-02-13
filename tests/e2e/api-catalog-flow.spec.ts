
import { test, expect } from '@playwright/test';

test.describe('API Catalog Flow', () => {
  let nonce: string;

  test.beforeEach(async ({ page }) => {
    // 1. Login
    await page.goto('/wp-login.php');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'stc54');
    await page.click('#wp-submit');
    await expect(page).toHaveTitle(/Dashboard/);

    // 2. Get Nonce by debugging dashboard
    await page.goto('/wp-admin/index.php');
    const content = await page.content();
    // Look for nonce pattern
    const match = content.match(/"nonce":"([a-f0-9]+)"/);
    if (match) {
        nonce = match[1];
        console.log('Found nonce in HTML:', nonce);
    } else {
        console.log('No nonce found in HTML. Content length:', content.length);
    }
    
    expect(nonce).toBeTruthy();
  });

  test('Create and retrieve catalog item', async ({ page }) => {
    const baseUrl = '/wp-json/pet/v1';

    // 1. Create a catalog item
    const createRes = await page.request.post(`${baseUrl}/catalog-items`, {
      headers: {
        'X-WP-Nonce': nonce
      },
      data: {
        name: 'Test Hosting Plan',
        sku: `HOST-${Date.now()}`,
        description: 'Premium managed hosting',
        category: 'Hosting',
        unit_price: 49.99,
        unit_cost: 10.00
      }
    });

    expect(createRes.ok()).toBeTruthy();
    const createData = await createRes.json();
    expect(createData.message).toBe('Catalog item created');
    
    // 2. List items to verify creation
    const listRes = await page.request.get(`${baseUrl}/catalog-items`, {
      headers: {
        'X-WP-Nonce': nonce
      }
    });
    
    expect(listRes.ok()).toBeTruthy();
    const items = await listRes.json();
    expect(Array.isArray(items)).toBeTruthy();
    
    const createdItem = items.find((i: any) => i.name === 'Test Hosting Plan');
    expect(createdItem).toBeTruthy();
    expect(createdItem.unit_price).toBe(49.99);
  });
});
