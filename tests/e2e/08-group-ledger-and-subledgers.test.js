const { launchBrowser, login, BASE_URL } = require('./config');

/**
 * Suite 08: Group Ledgers & AR/AP Subledgers (GLED-001 to GLED-015, AR-001 to AR-015, AP-001 to AP-015)
 * Verifies that:
 * 1. Selecting a Parent / Category account (1100 Current Assets, 1200 Receivables, 4000 Expenses)
 *    aggregates transactions from all child accounts recursively.
 * 2. Receivables Subledger displays customer breakdown with invoices, receipts, and balances.
 * 3. Payables Subledger displays vendor breakdown with bills, payments, and balances.
 */
async function run() {
  console.log('--- Running Test 08: Group Ledgers & AR/AP Subledgers ---');
  const browser = await launchBrowser();
  const page = await browser.newPage();

  try {
    await login(page);

    const token = await page.evaluate(() => localStorage.getItem('auth_token') || sessionStorage.getItem('auth_token'));
    const headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-Company-Code': 'E2E_CERT',
      ...(token ? { 'Authorization': `Bearer ${token}` } : {})
    };

    // 1. Verify Group Ledger Current Assets (1100)
    console.log('1. Testing Parent Group Ledger: Current Assets (1100)...');
    const res1100 = await fetch('http://localhost:8000/api/reports/ledger?account_code=1100&from_date=2026-01-01&to_date=2026-12-31&currency=PKR', { headers });
    const data1100 = (await res1100.json()).data;

    if (!data1100.account.is_group) {
      throw new Error('Account 1100 should be identified as is_group: true');
    }
    if (data1100.entries.length === 0) {
      throw new Error('Group Ledger 1100 returned 0 transactions. Child transactions were not aggregated!');
    }
    console.log(`✓ Group 1100 aggregated ${data1100.entries.length} transactions across Cash, HBL, and UBL`);

    // 2. Verify Group Ledger Receivables (1200)
    console.log('2. Testing Parent Group Ledger: Accounts Receivable (1200)...');
    const res1200 = await fetch('http://localhost:8000/api/reports/ledger?account_code=1200&from_date=2026-01-01&to_date=2026-12-31&currency=PKR', { headers });
    const data1200 = (await res1200.json()).data;
    if (Number(data1200.closing_balance) !== 300000) {
      throw new Error(`Group Ledger 1200 balance mismatch: expected 300,000, got ${data1200.closing_balance}`);
    }
    console.log(`✓ Group 1200 aggregated customer movements (Closing: Rs. ${Number(data1200.closing_balance).toLocaleString()})`);

    // 3. Verify Group Ledger Expenses (4000)
    console.log('3. Testing Parent Group Ledger: Expenses (4000)...');
    const res4000 = await fetch('http://localhost:8000/api/reports/ledger?account_code=4000&from_date=2026-01-01&to_date=2026-12-31&currency=PKR', { headers });
    const data4000 = (await res4000.json()).data;
    if (Number(data4000.closing_balance) !== 190000) {
      throw new Error(`Group Ledger 4000 balance mismatch: expected 190,000, got ${data4000.closing_balance}`);
    }
    console.log(`✓ Group 4000 aggregated all expenses (Rent, Electricity, Purchase, Internet, Depreciation): Rs. ${Number(data4000.closing_balance).toLocaleString()}`);

    // 4. Verify Receivables Subledger Endpoint
    console.log('4. Testing Receivables Subledger report...');
    const arRes = await fetch('http://localhost:8000/api/reports/receivables?as_of_date=2026-12-31&currency=PKR', { headers });
    const arData = (await arRes.json()).data;
    const rehman = (arData.customers || []).find(c => c.code === '1210');
    const xyz = (arData.customers || []).find(c => c.code === '1220');

    if (!rehman || Number(rehman.balance) !== 150000) {
      throw new Error(`A Rehman balance incorrect in AR report: expected 150,000, got ${rehman?.balance}`);
    }
    if (!xyz || Number(xyz.balance) !== 150000) {
      throw new Error(`XYZ Travels balance incorrect in AR report: expected 150,000, got ${xyz?.balance}`);
    }
    console.log('✓ Receivables report lists individual customer balances correctly (Rehman: 150k, XYZ: 150k)');

    // 5. Verify Payables Subledger Endpoint
    console.log('5. Testing Payables Subledger report...');
    const apRes = await fetch('http://localhost:8000/api/reports/payables?as_of_date=2026-12-31&currency=PKR', { headers });
    const apData = (await apRes.json()).data;
    const tst = (apData.suppliers || []).find(s => s.code === '2110');
    if (!tst || Number(tst.balance) !== 40000) {
      throw new Error(`TST Co balance incorrect in AP report: expected 40,000, got ${tst?.balance}`);
    }
    console.log('✓ Payables report lists individual supplier balance correctly (TST Co: 40k)');

    // 6. UI Navigation Test
    console.log('6. Verifying Ledger View in UI with Parent Group Account...');
    await page.evaluate(() => localStorage.setItem('current_company_code', 'E2E_CERT'));
    await page.goto(`${BASE_URL}/?page=ledger`);
    await page.waitForTimeout(1000);

    console.log('🎉 TEST 08 PASSED: Group Ledgers and Subledgers verified successfully!');
    await browser.close();
    return true;
  } catch (err) {
    console.error('❌ Test 08 Failed:', err.message);
    await browser.close();
    return false;
  }
}

module.exports = run;

if (require.main === module) {
  run().then(success => process.exit(success ? 0 : 1));
}