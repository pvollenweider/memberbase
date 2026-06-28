import { test, expect } from '@playwright/test';

test.describe('Compta (accounting)', () => {
  const USER_ID = 1; // Alice Dupont from seed

  test('view compta for a member', async ({ page }) => {
    await page.goto(`/index.php?view=compta&userid=${USER_ID}`);
    await page.waitForLoadState('networkidle');
    // The add-compta form should be present
    await expect(page.locator('form[name="addCompta"]')).toBeVisible();
    // The existing seed entry should appear in the table
    await expect(page.locator('table').last()).toBeVisible();
  });

  test('add a compta entry', async ({ page }) => {
    await page.goto(`/index.php?view=compta&userid=${USER_ID}`);
    await page.waitForLoadState('networkidle');

    const form = page.locator('form[name="addCompta"]');
    await form.locator('select[name="type_id"]').selectOption({ index: 0 });
    await form.locator('input[name="date"]').fill('01.01.2025');
    await form.locator('input[name="libele"]').fill('E2E test entry');
    await form.locator('input[name="sum"]').fill('99');
    await form.locator('button[type="submit"]').click();
    await page.waitForLoadState('networkidle');

    await expect(page.locator('text=E2E test entry')).toBeVisible();
  });

  test('edit an existing compta entry', async ({ page }) => {
    // compta id=1 belongs to user 1 per seed
    await page.goto(`/index.php?view=updateCompta&comptaid=1&userid=${USER_ID}`);
    await page.waitForLoadState('networkidle');

    await page.fill('#libele', 'Cotisation modifiee');
    await page.click('button[type="submit"].btn-primary');
    await page.waitForLoadState('networkidle');

    // Should redirect back to compta view
    await page.goto(`/index.php?view=updateCompta&comptaid=1&userid=${USER_ID}`);
    await expect(page.locator('#libele')).toHaveValue('Cotisation modifiee');
  });

  test('delete a compta entry via confirmation page', async ({ page }) => {
    // First add a fresh entry so we have a safe delete target
    await page.goto(`/index.php?view=compta&userid=${USER_ID}`);
    await page.waitForLoadState('networkidle');

    const form = page.locator('form[name="addCompta"]');
    await form.locator('select[name="type_id"]').selectOption({ index: 0 });
    await form.locator('input[name="date"]').fill('02.02.2025');
    await form.locator('input[name="libele"]').fill('ToDeleteEntry');
    await form.locator('input[name="sum"]').fill('10');
    await form.locator('button[type="submit"]').click();
    await page.waitForLoadState('networkidle');

    // Find the newly created row by its label and get its delete link
    const deleteLink = page.locator('a[href*="view=removeCompta"]').first();
    const href = await deleteLink.getAttribute('href');
    if (!href) throw new Error('Delete link not found');

    await page.goto(href.startsWith('/') ? href : '/' + href);
    await page.waitForLoadState('networkidle');

    // Click the confirm delete button (danger link)
    await page.locator('a.btn-danger').click();
    await page.waitForLoadState('networkidle');

    // Should be back on the compta view
    await expect(page.locator('form[name="addCompta"]')).toBeVisible();
  });
});
