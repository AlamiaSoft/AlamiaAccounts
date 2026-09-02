const { launchBrowser, login, navigateTo, BASE_URL } = require('./config');

async function run() {
  console.log('--- Running Test 05: Voucher Entry UX (Searchable Accounts & Auto-Sync) ---');
  const browser = await launchBrowser();
  const page = await browser.newPage();

  try {
    await login(page);

    // 1. Navigate to Journal Voucher
    await navigateTo(page, 'Vouchers', 'Journal Voucher');
    console.log('✓ Navigated to Journal Voucher');

    // 2. Verify voucher entry table renders
    const table = page.locator('table');
    if (await table.count() === 0) {
      throw new Error('Voucher line items table not found');
    }
    console.log('✓ Line items table is visible');

    // 3. Test entering numeric account code updates account name
    const firstRow = page.locator('table tbody tr').first();
    const codeInput = firstRow.locator('td').first().locator('input');
    await codeInput.fill('');
    await codeInput.fill('1130');
    await page.waitForTimeout(600);

    const nameInFirstRow = await firstRow.locator('td').nth(1).innerText();
    if (!nameInFirstRow.includes('Meezan Bank')) {
      throw new Error(`Expected account name to update to "Meezan Bank", got: "${nameInFirstRow}"`);
    }
    console.log(`✓ Account code 1130 auto-populated account name: "${nameInFirstRow.trim()}"`);

    // 4. Test selecting account by name via Combobox
    const secondRow = page.locator('table tbody tr').nth(1);
    const comboboxTrigger = secondRow.locator('td').nth(1).locator('[role="button"]').first();
    await comboboxTrigger.click();
    await page.waitForTimeout(400);

    // Type "Sales" in the search input
    const searchInput = secondRow.locator('input[placeholder*="Type name"]').first();
    if (await searchInput.isVisible()) {
      await searchInput.fill('Sales');
      await page.waitForTimeout(300);

      // Click the filtered option for Sales Revenue
      const salesOption = secondRow.locator('li').filter({ hasText: /Sales Revenue/i }).first();
      await salesOption.click();
      await page.waitForTimeout(500);

      const secondRowCode = await secondRow.locator('td').first().locator('input').inputValue();
      const secondRowName = await secondRow.locator('td').nth(1).innerText();

      if (secondRowCode !== '3100' || !secondRowName.includes('Sales Revenue')) {
        throw new Error(`Expected code 3100 and name Sales Revenue, got code: "${secondRowCode}", name: "${secondRowName}"`);
      }
      console.log(`✓ Selecting by name "Sales Revenue" auto-populated code: "${secondRowCode}" and name: "${secondRowName.trim()}"`);
    } else {
      throw new Error('Search input in AccountCombobox did not appear');
    }

    // 5. Test Double-Entry & Auto-Balance
    const debitInput = firstRow.locator('td').nth(2).locator('input');
    await debitInput.fill('15000');
    await page.waitForTimeout(400);

    // Check summary shows difference
    const summaryCard = page.locator('main').filter({ hasText: /Balance Status/i }).first();
    const summaryText = await summaryCard.innerText();
    if (!summaryText.includes('15,000')) {
      throw new Error('Summary card did not reflect 15,000 debit');
    }

    // Click Auto-Balance button
    const autoBalanceBtn = page.getByRole('button', { name: /Auto-Balance/i }).first();
    if (await autoBalanceBtn.isVisible()) {
      await autoBalanceBtn.click();
      await page.waitForTimeout(400);

      const finalSummaryText = await page.locator('main').innerText();
      if (!finalSummaryText.includes('Balanced ✓')) {
        throw new Error('Voucher was not marked as Balanced after auto-balance');
      }
      console.log('✓ Auto-balance successfully balanced debit and credit entries');
    }

    console.log('PASS: Test 05 completed successfully.\n');
    return true;
  } catch (err) {
    console.error(`FAIL: Test 05 failed: ${err.message}\n`);
    return false;
  } finally {
    await browser.close();
  }
}

if (require.main === module) {
  run().then(passed => process.exit(passed ? 0 : 1));
}

module.exports = run;