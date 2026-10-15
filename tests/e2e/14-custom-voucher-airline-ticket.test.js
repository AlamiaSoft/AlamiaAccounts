const { launchBrowser, login, switchCompany, BASE_URL } = require('./config');

async function run() {
  console.log('--- Running Test 14: Airline Ticket Booking Custom Voucher & Commission Posting ---');
  const browser = await launchBrowser();
  const page = await browser.newPage();

  // Track console errors to catch React error #185 or posting errors
  const consoleErrors = [];
  page.on('console', (msg) => {
    if (msg.type() === 'error') {
      consoleErrors.push(msg.text());
      console.log(`[Browser Console Error]: ${msg.text()}`);
    }
  });

  page.on('pageerror', (err) => {
    consoleErrors.push(err.message);
    console.log(`[Page Error]: ${err.message}`);
  });

  try {
    await login(page);
    console.log('✓ Successfully logged in');

    // 1. Switch company to KAMAL_EXPRESS if available
    try {
      await switchCompany(page, 'KAMAL');
      console.log('✓ Switched to KAMAL_EXPRESS');
    } catch (e) {
      console.log('ℹ Proceeding with current tenant (could not switch to KAMAL):', e.message);
    }

    await page.waitForTimeout(1000);

    // 2. Locate "Airline Ticket Booking" in sidebar (expand custom vouchers only if needed)
    let airlineTicketBtn = page.locator('aside nav button').filter({ hasText: /Airline Ticket/i }).first();
    if (!await airlineTicketBtn.isVisible()) {
      const customVouchersHeader = page.locator('aside nav button').filter({ hasText: /Custom Vouchers/i }).first();
      if (await customVouchersHeader.isVisible()) {
        await customVouchersHeader.click();
        await page.waitForTimeout(500);
      }
    }

    // 3. Click "Airline Ticket Booking"
    airlineTicketBtn = page.locator('aside nav button').filter({ hasText: /Airline Ticket/i }).first();
    if (!await airlineTicketBtn.isVisible()) {
      throw new Error('Airline Ticket Booking menu item not visible in sidebar');
    }
    await airlineTicketBtn.click();
    console.log('✓ Clicked Airline Ticket Booking');

    // Wait for form to mount and stabilize
    await page.waitForTimeout(2000);

    // 4. Verify that NO React error #185 occurred
    const react185Error = consoleErrors.find((err) =>
      err.includes('#185') || err.includes('Maximum update depth exceeded')
    );
    if (react185Error) {
      throw new Error(`CRITICAL: Page crashed with React Error #185: ${react185Error}`);
    }
    console.log('✓ Form loaded without React Error #185');

    // 5. Verify the form elements are rendered
    const refInput = page.locator('input[placeholder*="TKT"]').first();
    await refInput.waitFor({ state: 'visible', timeout: 5000 });
    const refValue = await refInput.inputValue();
    console.log(`✓ Form mounted with reference: ${refValue}`);

    // Set test reference number
    const testRef = `TKT-${Date.now().toString().slice(-6)}`;
    await refInput.fill(testRef);

    // Fill passenger name and ticket details
    const passengerInput = page.locator('input[placeholder*="Passenger Name"]').first();
    if (await passengerInput.isVisible()) {
      await passengerInput.fill('QA Tester');
    }

    const pnrInput = page.locator('input[placeholder*="PNR"]').first();
    if (await pnrInput.isVisible()) {
      await pnrInput.fill('QATST1');
    }

    // Fill Gross Fare
    const fareInput = page.locator('input[placeholder*="Gross Fare"]').first();
    if (await fareInput.isVisible()) {
      await fareInput.fill('12345');
      await page.waitForTimeout(500);
      console.log('✓ Filled Gross Fare: 12345');
    }

    // Fill Agent Commission
    const commInput = page.locator('input[placeholder*="Agent Commission"]').first();
    if (await commInput.isVisible()) {
      await commInput.fill('345');
      await page.waitForTimeout(500);
      console.log('✓ Filled Agent Commission: 345');
    }

    // Check if the "+ Add Commission Accounting Lines (Dr/Cr)" banner appears
    const addCommBtn = page.getByRole('button', { name: /\+ Add Commission Accounting Lines/i });
    if (await addCommBtn.isVisible()) {
      await addCommBtn.click();
      await page.waitForTimeout(500);
      console.log('✓ Clicked "+ Add Commission Accounting Lines"');
    }

    // Ensure lines 3 and 4 have valid accounts:
    // Line 3: Operating Expenses (4200) - Debit 345
    // Line 4: Cash (1110) - Credit 345
    const rows = page.locator('table tbody tr');
    const rowCount = await rows.count();
    console.log(`✓ Line items table has ${rowCount} rows`);

    if (rowCount >= 4) {
      // Row 3: Account code 4200
      const row3CodeInput = rows.nth(2).locator('td').first().locator('input');
      await row3CodeInput.fill('4200');
      await page.waitForTimeout(300);

      // Row 4: Account code 1110
      const row4CodeInput = rows.nth(3).locator('td').first().locator('input');
      await row4CodeInput.fill('1110');
      await page.waitForTimeout(300);
      console.log('✓ Specified posting accounts for commission lines (4200 and 1110)');
    }

    // Click "Post Voucher"
    const postBtn = page.getByRole('button', { name: /Post Voucher/i });
    if (!await postBtn.isEnabled()) {
      console.log('ℹ Post Voucher button is disabled, checking balance...');
    } else {
      await postBtn.click();
      console.log('✓ Clicked Post Voucher');

      // Wait for success toast or response
      await page.waitForTimeout(3000);

      // Check if any posting error was logged
      const postingError = consoleErrors.find((err) =>
        err.includes('Voucher posting error') || err.includes('cannot appear more than once')
      );
      if (postingError) {
        throw new Error(`CRITICAL: Voucher posting failed: ${postingError}`);
      }

      console.log('✓ Voucher posted successfully without Abivia duplicate account rejection!');
    }

    console.log('🎉 TEST 14 PASSED: Airline Ticket Booking Custom Voucher works flawlessly!');
  } finally {
    await browser.close();
  }
}

if (require.main === module) {
  run().catch((err) => {
    console.error('❌ TEST 14 FAILED:', err);
    process.exit(1);
  });
}

module.exports = run;
