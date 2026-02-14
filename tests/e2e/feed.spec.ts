import { test, expect, Page } from '@playwright/test';

const WP_USER = process.env.PET_USER || 'admin';
const WP_PASS = process.env.PET_PASS || 'stc54';

async function login(page: Page) {
  await page.goto('/wp-login.php', { waitUntil: 'domcontentloaded' });
  await page.fill('#user_login', WP_USER);
  await page.fill('#user_pass', WP_PASS);
  await page.click('#wp-submit');
  await page.waitForLoadState('domcontentloaded');
}

test.describe('Operational Feed', () => {
  test('create and acknowledge announcement', async ({ page, request }) => {
    await login(page);
    await page.goto('/wp-admin/admin.php?page=pet-activity', { waitUntil: 'domcontentloaded' });

    const nonce = await page.evaluate(() => (window as any).petSettings?.nonce);
    if (!nonce) {
      test.skip(true, 'Nonce unavailable; likely not logged in. Skipping.');
      return;
    }

    const uniqueTitle = `E2E Announcement ${Date.now()}`;

    const res = await request.post('/wp-json/pet/v1/announcements', {
      headers: { 'X-WP-Nonce': `${nonce}`, 'Content-Type': 'application/json' },
      data: {
        title: uniqueTitle,
        body: 'Automated test announcement',
        priorityLevel: 'normal',
        pinned: false,
        acknowledgementRequired: true,
        gpsRequired: false,
        audienceScope: 'global',
      },
    });
    expect(res.ok()).toBeTruthy();
    const ann = await res.json();
    expect(ann.title).toBe(uniqueTitle);

    await page.reload({ waitUntil: 'domcontentloaded' });
    const row = page.getByText(uniqueTitle, { exact: true });
    await expect(row).toBeVisible();

    const ackButton = row.locator('xpath=..').locator('text=Acknowledge').first();
    await ackButton.click();

    const ackAgain = await request.post(`/wp-json/pet/v1/announcements/${ann.id}/ack`, {
      headers: { 'X-WP-Nonce': `${nonce}`, 'Content-Type': 'application/json' },
      data: {},
    });
    expect(ackAgain.ok()).toBeTruthy();
    const ackJson = await ackAgain.json();
    expect(ackJson.message || '').toMatch(/Already acknowledged/i);
  });

  test('react to first feed event if present', async ({ page }) => {
    await login(page);
    await page.goto('/wp-admin/admin.php?page=pet-activity', { waitUntil: 'domcontentloaded' });

    const firstAckButton = page.locator('text=Events').locator('xpath=following::table[1]').locator('text=Ack').first();
    const hasEvents = await firstAckButton.isVisible();

    if (!hasEvents) {
      test.skip(true, 'No feed events available to react to');
      return;
    }

    await firstAckButton.click();
    await expect(firstAckButton).toBeVisible();
  });

  test('role-scoped announcement visible to assigned user', async ({ page, request }) => {
    await login(page);
    await page.goto('/wp-admin/admin.php?page=pet-activity', { waitUntil: 'domcontentloaded' });

    const nonce = await page.evaluate(() => (window as any).petSettings?.nonce);
    const apiUrl = await page.evaluate(() => (window as any).petSettings?.apiUrl);
    if (!nonce || !apiUrl) {
      test.skip(true, 'Nonce or apiUrl unavailable. Skipping.');
      return;
    }

    const meRes = await request.get('/wp-json/wp/v2/users/me', {
      headers: { 'X-WP-Nonce': `${nonce}` },
    });
    if (!meRes.ok()) {
      test.skip(true, 'Unable to fetch current WP user via REST. Skipping.');
      return;
    }
    const me = await meRes.json();
    const wpUserId: number = me.id;
    const email: string = me.email || me.user_email || 'admin@example.com';
    const name: string = me.name || me.display_name || 'Admin User';
    const firstName = String(name).split(' ')[0] || 'Admin';
    const lastName = String(name).split(' ').slice(1).join(' ') || 'User';

    const empRes = await request.get('/wp-json/pet/v1/employees', {
      headers: { 'X-WP-Nonce': `${nonce}` },
    });
    if (!empRes.ok()) {
      test.skip(true, 'Unable to list employees. Skipping.');
      return;
    }
    const emps = await empRes.json();
    let employee = (emps as any[]).find((e) => Number(e.wpUserId) === Number(wpUserId));

    if (!employee) {
      const createEmp = await request.post('/wp-json/pet/v1/employees', {
        headers: { 'X-WP-Nonce': `${nonce}`, 'Content-Type': 'application/json' },
        data: {
          wpUserId,
          firstName,
          lastName,
          email,
          status: 'active',
        },
      });
      expect(createEmp.ok()).toBeTruthy();
      const emps2 = await (await request.get('/wp-json/pet/v1/employees', { headers: { 'X-WP-Nonce': `${nonce}` } })).json();
      employee = (emps2 as any[]).find((e) => Number(e.wpUserId) === Number(wpUserId));
    }
    expect(employee).toBeTruthy();

    const uniqueRoleName = `E2E Role ${Date.now()}`;
    const roleCreate = await request.post('/wp-json/pet/v1/roles', {
      headers: { 'X-WP-Nonce': `${nonce}`, 'Content-Type': 'application/json' },
      data: { name: uniqueRoleName, level: 'senior', description: 'E2E role', success_criteria: 'Test' },
    });
    expect(roleCreate.ok()).toBeTruthy();

    const rolesRes = await request.get('/wp-json/pet/v1/roles', {
      headers: { 'X-WP-Nonce': `${nonce}` },
    });
    expect(rolesRes.ok()).toBeTruthy();
    const roles = await rolesRes.json();
    const role = (roles as any[]).find((r) => r.name === uniqueRoleName);
    expect(role).toBeTruthy();

    const publishRes = await request.post(`/wp-json/pet/v1/roles/${role.id}/publish`, {
      headers: { 'X-WP-Nonce': `${nonce}` },
      data: {},
    });
    expect(publishRes.ok()).toBeTruthy();

    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    const start_date = `${yyyy}-${mm}-${dd}`;

    const assignRes = await request.post('/wp-json/pet/v1/assignments', {
      headers: { 'X-WP-Nonce': `${nonce}`, 'Content-Type': 'application/json' },
      data: {
        employee_id: employee.id,
        role_id: role.id,
        start_date,
        allocation_pct: 100,
      },
    });
    expect(assignRes.ok()).toBeTruthy();
    const assignJson = await assignRes.json();

    const uniqueTitle = `E2E Role Announcement ${Date.now()}`;
    const annRes = await request.post('/wp-json/pet/v1/announcements', {
      headers: { 'X-WP-Nonce': `${nonce}`, 'Content-Type': 'application/json' },
      data: {
        title: uniqueTitle,
        body: 'Role-scoped announcement',
        priorityLevel: 'normal',
        pinned: false,
        acknowledgementRequired: false,
        gpsRequired: false,
        audienceScope: 'role',
        audienceReferenceId: String(role.id),
      },
    });
    expect(annRes.ok()).toBeTruthy();

    await page.reload({ waitUntil: 'domcontentloaded' });
    const row = page.getByText(uniqueTitle, { exact: true });
    await expect(row).toBeVisible();
    await expect(page.getByText(`role:${String(role.id)}`)).toBeVisible();

    const endRes = await request.post(`/wp-json/pet/v1/assignments/${assignJson.id}/end`, {
      headers: { 'X-WP-Nonce': `${nonce}`, 'Content-Type': 'application/json' },
      data: { end_date: start_date },
    });
    expect(endRes.ok()).toBeTruthy();
  });

  test('department-scoped announcement visible to team member', async ({ page, request }) => {
    await login(page);
    await page.goto('/wp-admin/admin.php?page=pet-activity', { waitUntil: 'domcontentloaded' });

    const nonce = await page.evaluate(() => (window as any).petSettings?.nonce);
    if (!nonce) {
      test.skip(true, 'Nonce unavailable; skipping.');
      return;
    }

    const meRes = await request.get('/wp-json/wp/v2/users/me', {
      headers: { 'X-WP-Nonce': `${nonce}` },
    });
    if (!meRes.ok()) {
      test.skip(true, 'Unable to fetch current WP user; skipping.');
      return;
    }
    const me = await meRes.json();
    const wpUserId: number = me.id;
    const email: string = me.email || me.user_email || 'admin@example.com';
    const name: string = me.name || me.display_name || 'Admin User';
    const firstName = String(name).split(' ')[0] || 'Admin';
    const lastName = String(name).split(' ').slice(1).join(' ') || 'User';

    const empRes = await request.get('/wp-json/pet/v1/employees', {
      headers: { 'X-WP-Nonce': `${nonce}` },
    });
    if (!empRes.ok()) {
      test.skip(true, 'Unable to list employees. Skipping.');
      return;
    }
    const emps = await empRes.json();
    let employee = (emps as any[]).find((e) => Number(e.wpUserId) === Number(wpUserId));

    if (!employee) {
      const createEmp = await request.post('/wp-json/pet/v1/employees', {
        headers: { 'X-WP-Nonce': `${nonce}`, 'Content-Type': 'application/json' },
        data: {
          wpUserId,
          firstName,
          lastName,
          email,
          status: 'active',
        },
      });
      expect(createEmp.ok()).toBeTruthy();
      const emps2 = await (await request.get('/wp-json/pet/v1/employees', { headers: { 'X-WP-Nonce': `${nonce}` } })).json();
      employee = (emps2 as any[]).find((e) => Number(e.wpUserId) === Number(wpUserId));
    }
    expect(employee).toBeTruthy();

    const uniqueTeamName = `E2E Team ${Date.now()}`;
    const teamRes = await request.post('/wp-json/pet/v1/teams', {
      headers: { 'X-WP-Nonce': `${nonce}`, 'Content-Type': 'application/json' },
      data: {
        name: uniqueTeamName,
        status: 'active',
        member_ids: [employee.id],
      },
    });
    expect(teamRes.ok()).toBeTruthy();

    const teamsRes = await request.get('/wp-json/pet/v1/teams', {
      headers: { 'X-WP-Nonce': `${nonce}` },
    });
    expect(teamsRes.ok()).toBeTruthy();
    const teams = await teamsRes.json();
    // Teams endpoint returns a tree; flatten to find by name
    const flatten = (nodes: any[]): any[] =>
      nodes.reduce((acc, n) => acc.concat([n], Array.isArray(n.children) ? flatten(n.children) : []), []);
    const teamFlat = flatten(Array.isArray(teams) ? teams : []);
    const team = teamFlat.find((t: any) => t.name === uniqueTeamName);
    expect(team).toBeTruthy();

    const uniqueTitle = `E2E Dept Announcement ${Date.now()}`;
    const annRes = await request.post('/wp-json/pet/v1/announcements', {
      headers: { 'X-WP-Nonce': `${nonce}`, 'Content-Type': 'application/json' },
      data: {
        title: uniqueTitle,
        body: 'Department-scoped announcement',
        priorityLevel: 'normal',
        pinned: false,
        acknowledgementRequired: false,
        gpsRequired: false,
        audienceScope: 'department',
        audienceReferenceId: String(team.id),
      },
    });
    expect(annRes.ok()).toBeTruthy();

    await page.reload({ waitUntil: 'domcontentloaded' });
    const row = page.getByText(uniqueTitle, { exact: true });
    await expect(row).toBeVisible();
    await expect(page.getByText(`department:${String(team.id)}`)).toBeVisible();
  });

  test('role-scoped announcement hidden when user not assigned', async ({ page, request }) => {
    await login(page);
    await page.goto('/wp-admin/admin.php?page=pet-activity', { waitUntil: 'domcontentloaded' });

    const nonce = await page.evaluate(() => (window as any).petSettings?.nonce);
    if (!nonce) {
      test.skip(true, 'Nonce unavailable; skipping.');
      return;
    }

    const uniqueRoleName = `E2E Role Unassigned ${Date.now()}`;
    const roleCreate = await request.post('/wp-json/pet/v1/roles', {
      headers: { 'X-WP-Nonce': `${nonce}`, 'Content-Type': 'application/json' },
      data: { name: uniqueRoleName, level: 'senior', description: 'Unassigned role', success_criteria: 'None' },
    });
    if (!roleCreate.ok()) {
      const errText = await roleCreate.text();
      test.skip(true, `Role create failed: ${errText}`);
      return;
    }

    const rolesRes = await request.get('/wp-json/pet/v1/roles', {
      headers: { 'X-WP-Nonce': `${nonce}` },
    });
    if (!rolesRes.ok()) {
      const errText = await rolesRes.text();
      test.skip(true, `List roles failed: ${errText}`);
      return;
    }
    const roles = await rolesRes.json();
    const role = (roles as any[]).find((r) => r.name === uniqueRoleName);
    expect(role).toBeTruthy();

    const publishRes = await request.post(`/wp-json/pet/v1/roles/${role.id}/publish`, {
      headers: { 'X-WP-Nonce': `${nonce}` },
      data: {},
    });
    if (!publishRes.ok()) {
      const errText = await publishRes.text();
      test.skip(true, `Publish role failed: ${errText}`);
      return;
    }

    const title = `E2E Role Hidden ${Date.now()}`;
    const annRes = await request.post('/wp-json/pet/v1/announcements', {
      headers: { 'X-WP-Nonce': `${nonce}`, 'Content-Type': 'application/json' },
      data: {
        title,
        body: 'Should not be visible to current user',
        priorityLevel: 'normal',
        pinned: false,
        acknowledgementRequired: false,
        gpsRequired: false,
        audienceScope: 'role',
        audienceReferenceId: String(role.id),
      },
    });
    if (!annRes.ok()) {
      const errText = await annRes.text();
      test.skip(true, `Create announcement failed: ${errText}`);
      return;
    }

    await page.reload({ waitUntil: 'domcontentloaded' });
    const row = page.getByText(title, { exact: true });
    await expect(row).toHaveCount(0);
  });

  test('acknowledge role-scoped announcement and enforce deduplication', async ({ page, request }) => {
    await login(page);
    await page.goto('/wp-admin/admin.php?page=pet-activity', { waitUntil: 'domcontentloaded' });

    const nonce = await page.evaluate(() => (window as any).petSettings?.nonce);
    if (!nonce) {
      test.skip(true, 'Nonce unavailable; skipping.');
      return;
    }

    const meRes = await request.get('/wp-json/wp/v2/users/me', {
      headers: { 'X-WP-Nonce': `${nonce}` },
    });
    if (!meRes.ok()) {
      test.skip(true, 'Unable to fetch current WP user; skipping.');
      return;
    }
    const me = await meRes.json();
    const wpUserId: number = me.id;
    const email: string = me.email || me.user_email || 'admin@example.com';
    const name: string = me.name || me.display_name || 'Admin User';
    const firstName = String(name).split(' ')[0] || 'Admin';
    const lastName = String(name).split(' ').slice(1).join(' ') || 'User';

    const empRes = await request.get('/wp-json/pet/v1/employees', {
      headers: { 'X-WP-Nonce': `${nonce}` },
    });
    if (!empRes.ok()) {
      test.skip(true, 'Unable to list employees. Skipping.');
      return;
    }
    const emps = await empRes.json();
    let employee = (emps as any[]).find((e) => Number(e.wpUserId) === Number(wpUserId));

    if (!employee) {
      const createEmp = await request.post('/wp-json/pet/v1/employees', {
        headers: { 'X-WP-Nonce': `${nonce}`, 'Content-Type': 'application/json' },
        data: {
          wpUserId,
          firstName,
          lastName,
          email,
          status: 'active',
        },
      });
      expect(createEmp.ok()).toBeTruthy();
      const emps2 = await (await request.get('/wp-json/pet/v1/employees', { headers: { 'X-WP-Nonce': `${nonce}` } })).json();
      employee = (emps2 as any[]).find((e) => Number(e.wpUserId) === Number(wpUserId));
    }
    expect(employee).toBeTruthy();

    const uniqueRoleName = `E2E Role Ack ${Date.now()}`;
    const roleCreate = await request.post('/wp-json/pet/v1/roles', {
      headers: { 'X-WP-Nonce': `${nonce}`, 'Content-Type': 'application/json' },
      data: { name: uniqueRoleName, level: 'senior', description: 'Ack role', success_criteria: 'Test' },
    });
    expect(roleCreate.ok()).toBeTruthy();

    const rolesRes = await request.get('/wp-json/pet/v1/roles', {
      headers: { 'X-WP-Nonce': `${nonce}` },
    });
    expect(rolesRes.ok()).toBeTruthy();
    const roles = await rolesRes.json();
    const role = (roles as any[]).find((r) => r.name === uniqueRoleName);
    expect(role).toBeTruthy();

    const publishRes = await request.post(`/wp-json/pet/v1/roles/${role.id}/publish`, {
      headers: { 'X-WP-Nonce': `${nonce}` },
      data: {},
    });
    expect(publishRes.ok()).toBeTruthy();

    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    const start_date = `${yyyy}-${mm}-${dd}`;

    const assignRes = await request.post('/wp-json/pet/v1/assignments', {
      headers: { 'X-WP-Nonce': `${nonce}`, 'Content-Type': 'application/json' },
      data: {
        employee_id: employee.id,
        role_id: role.id,
        start_date,
        allocation_pct: 100,
      },
    });
    expect(assignRes.ok()).toBeTruthy();
    const assignJson = await assignRes.json();

    const title = `E2E Role Ack Announcement ${Date.now()}`;
    const annRes = await request.post('/wp-json/pet/v1/announcements', {
      headers: { 'X-WP-Nonce': `${nonce}`, 'Content-Type': 'application/json' },
      data: {
        title,
        body: 'Role-scoped announcement requiring acknowledgement',
        priorityLevel: 'normal',
        pinned: false,
        acknowledgementRequired: true,
        gpsRequired: false,
        audienceScope: 'role',
        audienceReferenceId: String(role.id),
      },
    });
    expect(annRes.ok()).toBeTruthy();
    const ann = await annRes.json();

    await page.reload({ waitUntil: 'domcontentloaded' });
    const row = page.getByText(title, { exact: true });
    await expect(row).toBeVisible();
    const ackButton = row.locator('xpath=..').locator('text=Acknowledge').first();
    await ackButton.click();

    const ackAgain = await request.post(`/wp-json/pet/v1/announcements/${ann.id}/ack`, {
      headers: { 'X-WP-Nonce': `${nonce}`, 'Content-Type': 'application/json' },
      data: {},
    });
    expect(ackAgain.ok()).toBeTruthy();
    const ackJson = await ackAgain.json();
    expect((ackJson.message || '') as string).toMatch(/Already acknowledged/i);

    const endRes = await request.post(`/wp-json/pet/v1/assignments/${assignJson.id}/end`, {
      headers: { 'X-WP-Nonce': `${nonce}`, 'Content-Type': 'application/json' },
      data: { end_date: start_date },
    });
    expect(endRes.ok()).toBeTruthy();
  });

  test('department-scoped announcement hidden when user not in team', async ({ page, request }) => {
    await login(page);
    await page.goto('/wp-admin/admin.php?page=pet-activity', { waitUntil: 'domcontentloaded' });

    const nonce = await page.evaluate(() => (window as any).petSettings?.nonce);
    if (!nonce) {
      test.skip(true, 'Nonce unavailable; skipping.');
      return;
    }

    const uniqueTeamName = `E2E Team Hidden ${Date.now()}`;
    const teamRes = await request.post('/wp-json/pet/v1/teams', {
      headers: { 'X-WP-Nonce': `${nonce}`, 'Content-Type': 'application/json' },
      data: {
        name: uniqueTeamName,
        status: 'active',
        member_ids: [],
      },
    });
    if (!teamRes.ok()) {
      const errText = await teamRes.text();
      test.skip(true, `Create team failed: ${errText}`);
      return;
    }

    const teamsRes = await request.get('/wp-json/pet/v1/teams', {
      headers: { 'X-WP-Nonce': `${nonce}` },
    });
    if (!teamsRes.ok()) {
      const errText = await teamsRes.text();
      test.skip(true, `List teams failed: ${errText}`);
      return;
    }
    const teams = await teamsRes.json();
    const flatten = (nodes: any[]): any[] =>
      nodes.reduce((acc, n) => acc.concat([n], Array.isArray(n.children) ? flatten(n.children) : []), []);
    const teamFlat = flatten(Array.isArray(teams) ? teams : []);
    const team = teamFlat.find((t: any) => t.name === uniqueTeamName);
    expect(team).toBeTruthy();

    const title = `E2E Dept Hidden ${Date.now()}`;
    const annRes = await request.post('/wp-json/pet/v1/announcements', {
      headers: { 'X-WP-Nonce': `${nonce}`, 'Content-Type': 'application/json' },
      data: {
        title,
        body: 'Department-scoped announcement should be hidden',
        priorityLevel: 'normal',
        pinned: false,
        acknowledgementRequired: false,
        gpsRequired: false,
        audienceScope: 'department',
        audienceReferenceId: String(team.id),
      },
    });
    expect(annRes.ok()).toBeTruthy();

    await page.reload({ waitUntil: 'domcontentloaded' });
    const row = page.getByText(title, { exact: true });
    await expect(row).toHaveCount(0);
  });
});
