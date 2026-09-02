const { launchBrowser, login, navigateTo, BASE_URL } = require('./config');

async function run() {
  console.log('--- Running Test 01: Authentication & Navigation ---');
  const browser = await launchBrowser();
  const page = await browser.newPage();

  try {
    // 1. Login
    await login(page);
    console.log('✓ Successfully authenticated and loaded dashboard');

    // 2. Verify Page Title
    const title = await page.title();
    if (!title.includes('Alamia Accounts')) {
      throw new Error(`Expected title to include "Alamia Accounts", got: "${title}"`);
    }
    console.log(`✓ Page title verified: "${title}"`);

    // 3. Verify Sidebar Branding
    const brandHeader = await page.locator('aside h1').innerText();
    if (brandHeader !== 'Alamia Accounts') {
      throw new Error(`Expected sidebar header "Alamia Accounts", got: "${brandHeader}"`);
    }
    console.log(`✓ Sidebar branding verified: "${brandHeader}"`);

    // 4. Verify Active Company Header
    const activeHeader = await page.locator('header, main div').filter({ hasText: /Active:/ }).first().innerText();
    console.log(`✓ Active Company confirmed: ${activeHeader.replace('\n', ' ')}`);

    console.log('PASS: Test 01 completed successfully.\n');
    return true;
  } catch (err) {
    console.error(`FAIL: Test 01 failed: ${err.message}\n`);
    return false;
  } finally {
    await browser.close();
  }
}

if (require.main === module) {
  run().then(passed => process.exit(passed ? 0 : 1));
}

module.exports = run;
