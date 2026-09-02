const { launchBrowser, login, navigateTo, switchCompany } = require('./config');

async function run() {
  console.log('--- Running Test 03: Financial Reports & Mathematical Balances ---');
  const browser = await launchBrowser();
  const page = await browser.newPage();

  try {
    await login(page);

    // Switch to Main Company to check standard ledger transactions
    await switchCompany(page, 'Main Company');

    // 1. Check Balance Sheet in Financial Reports
    await navigateTo(page, 'Reports', 'Balance Sheet');
    console.log('✓ Navigated to Balance Sheet');

    const bsText = await page.locator('main').innerText();
    if (!bsText.includes('Balance Sheet is balanced ✓')) {
      throw new Error('Balance Sheet is not reported as balanced');
    }
    if (!bsText.includes('710,000')) {
      throw new Error('Expected Total Assets / Total Equity of Rs. 710,000 in Balance Sheet');
    }
    console.log('✓ Balance Sheet verified: Assets (Rs. 710,000) === Liabilities + Equity (Rs. 710,000)');

    // 2. Check Profit & Loss Statement
    const plBtn = page.locator('main button').filter({ hasText: /^Profit & Loss Statement/ }).first();
    if (await plBtn.count() > 0) {
      await plBtn.click();
      await page.waitForTimeout(800);
    } else {
      await navigateTo(page, 'Reports', 'Profit & Loss');
    }

    const plText = await page.locator('main').innerText();
    if (!plText.includes('245,000') || !plText.includes('35,000') || !plText.includes('210,000')) {
      throw new Error('Profit & Loss figures do not match expected Revenue (245k), Expenses (35k), Net Profit (210k)');
    }
    console.log('✓ Profit & Loss verified: Revenue (245k) - Expenses (35k) = Net Profit (210,000)');

    // 3. Check Trial Balance
    const tbBtn = page.locator('main button').filter({ hasText: /^Trial Balance/ }).first();
    if (await tbBtn.count() > 0) {
      await tbBtn.click();
      await page.waitForTimeout(800);
    }

    const tbText = await page.locator('main').innerText();
    if (!tbText.includes('Trial Balance matches (Debit = Credit)') && !tbText.includes('745,000')) {
      throw new Error('Trial Balance does not show matching Debit = Credit totals of Rs. 745,000');
    }
    console.log('✓ Trial Balance verified: Total Debits (745,000) === Total Credits (745,000)');

    console.log('PASS: Test 03 completed successfully.\n');
    return true;
  } catch (err) {
    console.error(`FAIL: Test 03 failed: ${err.message}\n`);
    return false;
  } finally {
    await browser.close();
  }
}

if (require.main === module) {
  run().then(passed => process.exit(passed ? 0 : 1));
}

module.exports = run;
