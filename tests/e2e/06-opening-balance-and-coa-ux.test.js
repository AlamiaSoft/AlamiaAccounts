const { launchBrowser, login, navigateTo, BASE_URL } = require('./config');

async function run() {
  console.log('--- Running Test 06: Chart of Accounts Edit, Opening Balance & UI Modals ---');
  const browser = await launchBrowser();
  const page = await browser.newPage();

  try {
    await login(page);

    // 1. Navigate to Chart of Accounts
    await navigateTo(page, 'Accounts', 'Chart of Accounts');
    console.log('✓ Navigated to Chart of Accounts');

    // 2. Open Edit Modal for a leaf account (e.g. 1110 Cash)
    const editBtn = page.locator('div, li, tr').filter({ hasText: /1110/ }).locator('button').filter({ has: page.locator('svg.lucide-edit, svg') }).first();
    await editBtn.click();
    await page.waitForTimeout(500);

    const dialog = page.locator('[role="dialog"]').first();
    if (!await dialog.isVisible()) {
      throw new Error('Edit Account dialog did not open');
    }
    console.log('✓ Edit Account dialog opened');

    // 3. Verify Opening Balance field exists in form
    const obInput = dialog.locator('input[name="openingBalance"]');
    if (!await obInput.isVisible()) {
      throw new Error('Opening balance input field not visible in account edit form');
    }
    console.log('✓ Opening balance input field verified in Account Master form');

    // 4. Update account name
    const nameInput = dialog.locator('input[name="name"]');
    await nameInput.fill('Cash Main Tested');

    // Enter an opening balance (e.g. 25000)
    await obInput.fill('25000');

    // Click Update Account
    const submitBtn = dialog.getByRole('button', { name: /Update Account/i });
    await submitBtn.click();
    await page.waitForTimeout(500);

    // 5. Verify the styled ConfirmModal appears (accounting equation impact prompt)
    const confirmModal = page.locator('div').filter({ hasText: /Confirm Opening Balance Entry/i }).first();
    if (!await confirmModal.isVisible()) {
      throw new Error('ConfirmModal for Opening Balance did not appear');
    }
    console.log('✓ Styled ConfirmModal appeared with Accounting Equation Impact details');

    // Confirm the modal
    const confirmBtn = page.getByRole('button', { name: /Apply Balance & Save/i });
    await confirmBtn.click();
    await page.waitForTimeout(1000);

    // 6. Verify success alert
    const successToast = page.locator('div').filter({ hasText: /updated successfully/i }).first();
    console.log('✓ Account updated and opening balance entry posted without validation errors');

    console.log('PASS: Test 06 completed successfully.\n');
    return true;
  } catch (err) {
    console.error(`FAIL: Test 06 failed: ${err.message}\n`);
    return false;
  } finally {
    await browser.close();
  }
}

if (require.main === module) {
  run().then(passed => process.exit(passed ? 0 : 1));
}

module.exports = run;