import { test, expect } from '@playwright/test';

test.describe('Quote Once-off Project Blocks', () => {
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

  test('project phases and units roll up into section and quote totals', async ({ page }) => {
    page.on('console', (msg) => console.log('BROWSER LOG:', msg.text()));

    await page.goto('/wp-admin/admin.php?page=pet-quotes-sales');
    await expect(page.locator('h1', { hasText: 'PET - Quotes & Sales' })).toBeVisible();

    await page.click('button:has-text("Quotes")');

    await page.click('button:has-text("Start building quote")');

    await page.waitForSelector('select');
    await page.selectOption('select', { index: 1 });

    await page.fill(
      'input[placeholder="e.g. Q123 - Server Upgrade"]',
      'Once-off Project Phases Quote'
    );
    await page.fill('textarea', 'Quote for testing once-off project phases and units');

    await page.click('button:has-text("Start building quote")');

    await expect(page.locator('h2', { hasText: 'Quote #' })).toBeVisible({ timeout: 20000 });

    const addSectionFab = page.getByRole('button', { name: 'Add Section' }).first();
    await expect(addSectionFab).toBeVisible({ timeout: 10000 });
    await addSectionFab.click();

    const sectionCard = page.locator('div').filter({ hasText: 'New Section' }).first();
    await expect(sectionCard).toBeVisible({ timeout: 10000 });

    const addBlockButton = sectionCard.getByRole('button', { name: '+ Add Block' }).first();
    await expect(addBlockButton).toBeVisible({ timeout: 10000 });
    await addBlockButton.click();

    await expect(page.locator('text=Select Block Type')).toBeVisible({ timeout: 10000 });
    await page.getByRole('button', { name: 'Once-off Project' }).click();

    const projectRow = page.locator('tr', { hasText: 'OnceOffProjectBlock' }).first();
    await expect(projectRow).toBeVisible({ timeout: 20000 });

    const editorRow = page
      .locator('tr')
      .filter({ hasText: 'Project Description' })
      .first();
    await expect(editorRow).toBeVisible({ timeout: 10000 });

    const descriptionInput = editorRow.locator('input[type="text"]').first();
    await descriptionInput.fill('Cleaning & Decorating Project');

    const addPhaseButton = page.getByRole('button', { name: 'Add Phase' }).first();
    await addPhaseButton.click();

    const phasePanels = page.locator('[data-test="project-phase-panel"]');
    await expect(phasePanels.first()).toBeVisible({ timeout: 10000 });

    const preparePhase = phasePanels.nth(0);
    const prepareNameInput = preparePhase.locator('input[placeholder="Phase name"]');
    await prepareNameInput.fill('Prepare');

    const prepareAddUnit = preparePhase.getByRole('button', { name: 'Add Unit' });
    await prepareAddUnit.click();
    await prepareAddUnit.click();

    const prepareUnitsTable = preparePhase.locator('[data-test="project-phase-units"]');
    const prepareUnitRows = prepareUnitsTable.locator('tbody tr');

    const sweepRow = prepareUnitRows.nth(0);
    const sweepDescription = sweepRow.locator('input[type="text"]').first();
    const sweepQty = sweepRow.locator('input[type="number"]').nth(0);
    const sweepPrice = sweepRow.locator('input[type="number"]').nth(1);
    await sweepDescription.fill('Sweep');
    await sweepQty.fill('1');
    await sweepPrice.fill('100');

    const washRow = prepareUnitRows.nth(1);
    const washDescription = washRow.locator('input[type="text"]').first();
    const washQty = washRow.locator('input[type="number"]').nth(0);
    const washPrice = washRow.locator('input[type="number"]').nth(1);
    await washDescription.fill('Wash windows');
    await washQty.fill('1');
    await washPrice.fill('150');

    await addPhaseButton.click();

    const decoratePhase = phasePanels.nth(1);
    const decorateNameInput = decoratePhase.locator('input[placeholder="Phase name"]');
    await decorateNameInput.fill('Decorate');

    const decorateAddUnit = decoratePhase.getByRole('button', { name: 'Add Unit' });
    await decorateAddUnit.click();

    const decorateUnitsTable = decoratePhase.locator('[data-test="project-phase-units"]');
    const decorateUnitRow = decorateUnitsTable.locator('tbody tr').nth(0);
    const decorateDescription = decorateUnitRow.locator('input[type="text"]').first();
    const decorateQty = decorateUnitRow.locator('input[type="number"]').nth(0);
    const decoratePrice = decorateUnitRow.locator('input[type="number"]').nth(1);
    await decorateDescription.fill('Hang posters');
    await decorateQty.fill('2');
    await decoratePrice.fill('50');

    await page.getByRole('button', { name: 'Save' }).click();

    await expect(page.locator('text=Section Total: $350.00')).toBeVisible({ timeout: 20000 });

    await expect(
      page.locator('p', { hasText: 'Total Value:' }).locator('text=$350.00')
    ).toBeVisible({ timeout: 20000 });
  });
});
