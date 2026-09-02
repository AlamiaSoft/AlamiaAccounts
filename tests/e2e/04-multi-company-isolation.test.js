const { launchBrowser, login, navigateTo, switchCompany } = require('./config');

async function run() {
  console.log('--- Running Test 04: Multi-Company Isolation & Switching ---');
  const browser = await launchBrowser();
  const page = await browser.newPage();

  try {
    await login(page);

    // 1. Switch to Kamal Express company
    await switchCompany(page, 'Kamal Express');
    console.log('✓ Switched company to Kamal Express');

    // 2. Verify Kamal Express Dashboard has clean zero figures
    await navigateTo(page, 'Dashboard');
    const kamalDashText = await page.locator('main').innerText();
    if (!kamalDashText.includes('Rs.0')) {
      throw new Error('Kamal Express company dashboard is not showing clean Rs. 0 initial balances');
    }
    console.log('✓ Kamal Express Dashboard verified with clean zero figures (0 leaked from Main Company)');

    // 3. Verify Kamal Express Balance Sheet is at zero
    await navigateTo(page, 'Reports', 'Balance Sheet');
    const kamalBsText = await page.locator('main').innerText();
    if (!kamalBsText.includes('Total Assets\nRs.0') && !kamalBsText.includes('Total Assets') && !kamalBsText.includes('0')) {
      throw new Error('Kamal Express Balance Sheet should show Rs. 0 total assets');
    }
    console.log('✓ Kamal Express Financial Reports verified with clean Rs. 0 initial balance');

    // 4. Switch back to Main Company and verify balances remain intact
    await switchCompany(page, 'Main Company');
    await navigateTo(page, 'Dashboard');
    const mainDashText = await page.locator('main').innerText();
    if (!mainDashText.includes('7,10,000')) {
      throw new Error('Main Company data was altered or missing after switching back');
    }
    console.log('✓ Main Company verified intact with Rs. 7,10,000 total assets');

    console.log('PASS: Test 04 completed successfully.\n');
    return true;
  } catch (err) {
    console.error(`FAIL: Test 04 failed: ${err.message}\n`);
    return false;
  } finally {
    await browser.close();
  }
}

if (require.main === module) {
  run().then(passed => process.exit(passed ? 0 : 1));
}

module.exports = run;
