const { launchBrowser, login, navigateTo } = require('./config');

async function run() {
  console.log('--- Running Test 02: Chart of Accounts & Hierarchy ---');
  const browser = await launchBrowser();
  const page = await browser.newPage();

  try {
    await login(page);

    // 1. Navigate to Chart of Accounts
    await navigateTo(page, 'Masters', 'Chart of Accounts');
    console.log('✓ Navigated to Chart of Accounts');

    // 2. Verify Root Categories in Tree View
    const mainText = await page.locator('main').innerText();
    const requiredCategories = ['1000', 'Assets', '2000', 'Liabilities', '3000', 'Equity', '4000', 'Expenses', '5000', 'Revenue'];
    for (const item of requiredCategories) {
      if (!mainText.includes(item)) {
        throw new Error(`Tree View is missing expected category or code: ${item}`);
      }
    }
    console.log('✓ All 5 fundamental root categories verified in Tree View');

    // 3. Verify Bank Accounts Folder (1120) and Leaf Accounts (1130)
    if (!mainText.includes('1120') || !mainText.includes('Bank Accounts')) {
      throw new Error('Tree View is missing 1120 Bank Accounts folder');
    }
    if (!mainText.includes('1130') || !mainText.includes('Meezan Bank')) {
      throw new Error('Tree View is missing 1130 Meezan Bank leaf account under Bank Accounts');
    }
    console.log('✓ Verified 1120 Bank Accounts folder and nested 1130 Meezan Bank');

    // 4. Test "Add Account" Modal & Parent Dropdown Population
    await page.getByRole('button', { name: 'Add Account' }).click();
    await page.waitForTimeout(400);

    await page.locator('button').filter({ hasText: /No Parent|Select parent account|Assets/ }).first().click();
    await page.waitForTimeout(300);

    const parentOptions = await page.locator('[role="option"]').allInnerTexts();
    if (parentOptions.length < 5) {
      throw new Error(`Parent Account dropdown has insufficient options (${parentOptions.length})`);
    }
    console.log(`✓ Add Account modal verified with ${parentOptions.length} parent category options`);

    // Close modal
    await page.keyboard.press('Escape');

    console.log('PASS: Test 02 completed successfully.\n');
    return true;
  } catch (err) {
    console.error(`FAIL: Test 02 failed: ${err.message}\n`);
    return false;
  } finally {
    await browser.close();
  }
}

if (require.main === module) {
  run().then(passed => process.exit(passed ? 0 : 1));
}

module.exports = run;
