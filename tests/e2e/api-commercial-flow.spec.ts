import { test, expect } from '@playwright/test';

test.describe('Commercial API Flow', () => {
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
        // console.log('Snippet:', content.substring(0, 2000));
    }
    
    expect(nonce).toBeTruthy();
  });

  test('full quote to project flow via API', async ({ page }) => {
    // 1. Create Quote
    const createRes = await page.request.post('/wp-json/pet/v1/quotes', {
        headers: { 'X-WP-Nonce': nonce },
        data: {
            customerId: 1, // Assuming customer 1 exists (from seed data or previous tests)
            totalValue: 0,
            currency: 'USD'
        }
    });
    
    // If 404, maybe plugin not active or permalinks not flushed. But assume environment is good.
    expect(createRes.ok()).toBeTruthy();
    const quote = await createRes.json();
    console.log('Created Quote ID:', quote.id);
    expect(quote.id).toBeTruthy();
    expect(quote.state).toBe('draft');

    // 2. Add Implementation Component
    const addCompRes = await page.request.post(`/wp-json/pet/v1/quotes/${quote.id}/components`, {
        headers: { 'X-WP-Nonce': nonce },
        data: {
            type: 'implementation',
            data: {
                description: 'Web Dev Implementation',
                milestones: [
                    {
                        description: 'Phase 1',
                        tasks: [
                            {
                                description: 'Setup',
                                duration_hours: 10,
                                complexity: 1,
                                internal_cost: 50,
                                sell_rate: 100
                            }
                        ]
                    }
                ]
            }
        }
    });
    
    expect(addCompRes.ok()).toBeTruthy();
    const updatedQuote = await addCompRes.json();
    const comp = updatedQuote.components.find((c: any) => c.type === 'implementation');
    expect(comp).toBeTruthy();
    expect(comp.sellValue).toBe(1000); // 10 * 100

    // 3. Send Quote (required transition before Accept)
    const sendRes = await page.request.post(`/wp-json/pet/v1/quotes/${quote.id}/send`, {
        headers: { 'X-WP-Nonce': nonce }
    });
    
    if (!sendRes.ok()) {
        console.log('Send Quote Failed:', sendRes.status());
        console.log('Response Body:', await sendRes.text());
    }
    expect(sendRes.ok()).toBeTruthy();
    const sentQuote = await sendRes.json();
    expect(sentQuote.state).toBe('sent');

    // 4. Accept Quote
    const acceptRes = await page.request.post(`/wp-json/pet/v1/quotes/${quote.id}/accept`, {
        headers: { 'X-WP-Nonce': nonce }
    });
    
    if (!acceptRes.ok()) {
        console.log('Accept Quote Failed:', acceptRes.status());
        console.log('Response Body:', await acceptRes.text());
    }
    
    expect(acceptRes.ok()).toBeTruthy();
    const acceptedQuote = await acceptRes.json();
    expect(acceptedQuote.state).toBe('accepted');
    expect(acceptedQuote.acceptedAt).toBeTruthy();

    // 4. Verify Project Creation (via Project API)
    // The CreateProjectFromQuoteListener should have created a project with tasks.
    const projectsRes = await page.request.get('/wp-json/pet/v1/projects', {
        headers: { 'X-WP-Nonce': nonce }
    });
    
    expect(projectsRes.ok()).toBeTruthy();
    const projects = await projectsRes.json();
    const project = projects.find((p: any) => p.customerId === 1 && p.name === `Project for Quote #${quote.id}`);
    
    expect(project).toBeTruthy();
    expect(project.soldHours).toBe(10);
    expect(project.tasks.length).toBe(1);
    expect(project.tasks[0].name).toBe('Setup');
    expect(project.tasks[0].estimatedHours).toBe(10);

  });
});
