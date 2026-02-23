import { chromium, request, FullConfig } from '@playwright/test';

export default async function globalSetup(config: FullConfig) {
  const baseURL = config.projects[0].use.baseURL as string;
  const user = process.env.PET_USER || 'admin';
  const pass = process.env.PET_PASS || 'stc54';

  const browser = await chromium.launch();
  const page = await browser.newPage();
  await page.goto(`${baseURL}/wp-login.php`);
  await page.fill('#user_login', user);
  await page.fill('#user_pass', pass);
  await Promise.all([page.waitForNavigation(), page.click('#wp-submit')]);
  await page.goto(`${baseURL}/wp-admin/admin.php?page=pet-people`);

  const nonce = await page.evaluate(() => (window as any).petSettings?.nonce);
  const storageState = await page.context().storageState();
  const apiCtx = await request.newContext({ baseURL, ignoreHTTPSErrors: true, storageState });
  if (nonce) {
    await apiCtx.post('/wp-json/pet/v1/system/run-demo', {
      headers: { 'X-WP-Nonce': `${nonce}` },
      data: {},
    });
    await apiCtx.post('/wp-json/pet/v1/system/seed-demo', {
      headers: { 'X-WP-Nonce': `${nonce}` },
      data: {},
    });
  }
  await browser.close();
}
