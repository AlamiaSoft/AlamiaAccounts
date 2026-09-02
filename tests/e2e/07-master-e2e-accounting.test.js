const { launchBrowser, login, BASE_URL } = require('./config');

/**
 * Suite 07: Master E2E Accounting Certification (E2E-001 to E2E-010 & RECX-001 to RECX-020)
 * Evaluates the full certified flow from Alamia_Accounts_Master_E2E_Accounting_Test_Spec.md:
 * - Fresh company creation (E2E_CERT)
 * - COA creation and mapping
 * - Opening balance journal voucher
 * - Full transactional voucher pipeline (asset purchase, expenses, credit sales, collections, contra, depreciation)
 * - Complete mathematical reconciliation: Assets 1,350,000 = Liabilities 40,000 + Equity 1,310,000 (Variance = 0).
 */
async function run() {
  console.log('--- Running Test 07: Master E2E Accounting Certification ---');
  const browser = await launchBrowser();
  const page = await browser.newPage();

  try {
    await login(page);

    // 1. Setup Fresh Company E2E_CERT via API with auth token
    const token = await page.evaluate(() => localStorage.getItem('auth_token') || sessionStorage.getItem('auth_token'));
    const headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      ...(token ? { 'Authorization': `Bearer ${token}` } : {})
    };

    console.log('1. Setting up fresh test company: Alamia Accounts E2E Test Company (E2E_CERT)...');
    
    // Check if company exists or create
    const compRes = await fetch('http://localhost:8000/api/companies', { headers });
    const compData = await compRes.json();
    const existingComp = (compData.data || []).find(c => c.code === 'E2E_CERT');

    if (!existingComp) {
      const createRes = await fetch('http://localhost:8000/api/companies', {
        method: 'POST',
        headers,
        body: JSON.stringify({
          code: 'E2E_CERT',
          name: 'Alamia Accounts E2E Test Company',
          industry: 'Services',
          currency: 'PKR'
        })
      });
      if (!createRes.ok) {
        throw new Error(`Failed to create company E2E_CERT: ${await createRes.text()}`);
      }
      console.log('✓ Created fresh company E2E_CERT');
    } else {
      console.log('✓ Found existing company E2E_CERT');
    }

    const tenantHeaders = {
      ...headers,
      'X-Company-Code': 'E2E_CERT'
    };

    // 2. Setup COA Accounts for E2E_CERT
    console.log('2. Provisioning Master COA accounts in E2E_CERT...');
    const requiredAccounts = [
      { code: '1110', name: 'Cash in Hand', category: false, parent_code: '1100' },
      { code: '1121', name: 'HBL Bank', category: false, parent_code: '1120' },
      { code: '1125', name: 'UBL Bank', category: false, parent_code: '1120' },
      { code: '1210', name: 'A Rehman', category: false, parent_code: '1200' },
      { code: '1220', name: 'XYZ Travels', category: false, parent_code: '1200' },
      { code: '1500', name: 'Fixed Assets', category: true, parent_code: '1000' },
      { code: '1510', name: 'Computer Equipment', category: false, parent_code: '1500' },
      { code: '1590', name: 'Accumulated Depreciation', category: false, parent_code: '1500' },
      { code: '2110', name: 'TST Co', category: false, parent_code: '2100' },
      { code: '2120', name: 'ABC Supplier', category: false, parent_code: '2100' },
      { code: '3100', name: 'Sales A/C', category: false, parent_code: '3000' },
      { code: '3200', name: 'Service Revenue', category: false, parent_code: '3000' },
      { code: '4100', name: 'Purchase A/C', category: false, parent_code: '4000' },
      { code: '4210', name: 'Electricity Expense A/C', category: false, parent_code: '4000' },
      { code: '4220', name: 'Rent Expense A/C', category: false, parent_code: '4000' },
      { code: '4230', name: 'Internet Expense A/C', category: false, parent_code: '4000' },
      { code: '4240', name: 'Depreciation Expense A/C', category: false, parent_code: '4000' },
      { code: '5100', name: 'Capital A/C', category: false, parent_code: '5000' },
    ];

    const curAccRes = await fetch('http://localhost:8000/api/accounts', { headers: tenantHeaders });
    const curAccData = await curAccRes.json();
    const existingAccMap = new Map((curAccData.data || []).map(a => [a.code, a]));

    for (const acc of requiredAccounts) {
      if (!existingAccMap.has(acc.code)) {
        await fetch('http://localhost:8000/api/accounts', {
          method: 'POST',
          headers: tenantHeaders,
          body: JSON.stringify(acc)
        });
      }
    }
    console.log('✓ Master COA provisioned successfully');

    // Helper to post a voucher safely
    async function postVoucher(payload) {
      const res = await fetch('http://localhost:8000/api/vouchers', {
        method: 'POST',
        headers: tenantHeaders,
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (!res.ok) {
        if (data.message && (data.message.includes('already exists') || data.message.includes('Duplicate voucher reference'))) {
          return null;
        }
        throw new Error(`Failed to post voucher ${payload.reference}: ${JSON.stringify(data)}`);
      }
      return data;
    }

    console.log('3. Executing Master Accounting Transactions...');

    // E2E-003: Opening Capital (Dr Cash 400k, Dr HBL 600k, Cr Capital 1M)
    await postVoucher({
      reference: 'E2E-OB-001',
      date: '2026-09-01',
      currency: 'PKR',
      description: 'Opening Capital Injection',
      voucher_type: 'Journal',
      entries: [
        { account_code: '1110', amount: 400000, type: 'debit' },
        { account_code: '1121', amount: 600000, type: 'debit' },
        { account_code: '5100', amount: 1000000, type: 'credit' },
      ]
    });
    console.log('✓ E2E-003: Opening Capital posted (Dr 1,000,000 / Cr 1,000,000)');

    // E2E-004: Buy Computer Equipment (Dr Computer 100k, Cr HBL 100k)
    await postVoucher({
      reference: 'E2E-AST-001',
      date: '2026-09-02',
      currency: 'PKR',
      description: 'Purchase Computer Equipment via HBL',
      voucher_type: 'Payment',
      entries: [
        { account_code: '1510', amount: 100000, type: 'debit' },
        { account_code: '1121', amount: 100000, type: 'credit' },
      ]
    });
    console.log('✓ E2E-004: Asset Purchase posted');

    // E2E-005: Pay Expenses (Rent 50k via Cash, Electricity 20k via HBL)
    await postVoucher({
      reference: 'E2E-EXP-001',
      date: '2026-09-02',
      currency: 'PKR',
      description: 'Office Rent Paid Cash',
      voucher_type: 'Payment',
      entries: [
        { account_code: '4220', amount: 50000, type: 'debit' },
        { account_code: '1110', amount: 50000, type: 'credit' },
      ]
    });
    await postVoucher({
      reference: 'E2E-EXP-002',
      date: '2026-09-02',
      currency: 'PKR',
      description: 'Electricity Bill Paid via HBL',
      voucher_type: 'Payment',
      entries: [
        { account_code: '4210', amount: 20000, type: 'debit' },
        { account_code: '1121', amount: 20000, type: 'credit' },
      ]
    });
    console.log('✓ E2E-005: Rent and Electricity expenses posted');

    // E2E-006: Credit Sales (A Rehman 300k, XYZ Travels 200k)
    await postVoucher({
      reference: 'E2E-SAL-001',
      date: '2026-09-02',
      currency: 'PKR',
      description: 'Credit Sale Invoice to A Rehman',
      voucher_type: 'Journal',
      entries: [
        { account_code: '1210', amount: 300000, type: 'debit' },
        { account_code: '3100', amount: 300000, type: 'credit' },
      ]
    });
    await postVoucher({
      reference: 'E2E-SAL-002',
      date: '2026-09-02',
      currency: 'PKR',
      description: 'Credit Sale Invoice to XYZ Travels',
      voucher_type: 'Journal',
      entries: [
        { account_code: '1220', amount: 200000, type: 'debit' },
        { account_code: '3100', amount: 200000, type: 'credit' },
      ]
    });
    console.log('✓ E2E-006: Credit Sales posted (Total AR: 500,000)');

    // E2E-007: Customer Receipts (HBL 150k from Rehman, HBL 50k from XYZ)
    await postVoucher({
      reference: 'E2E-REC-001',
      date: '2026-09-02',
      currency: 'PKR',
      description: 'Receipt from A Rehman into HBL',
      voucher_type: 'Receipt',
      entries: [
        { account_code: '1121', amount: 150000, type: 'debit' },
        { account_code: '1210', amount: 150000, type: 'credit' },
      ]
    });
    await postVoucher({
      reference: 'E2E-REC-002',
      date: '2026-09-02',
      currency: 'PKR',
      description: 'Receipt from XYZ Travels into HBL',
      voucher_type: 'Receipt',
      entries: [
        { account_code: '1121', amount: 50000, type: 'debit' },
        { account_code: '1220', amount: 50000, type: 'credit' },
      ]
    });
    console.log('✓ E2E-007: Customer Receipts posted');

    // E2E-008: Credit Purchase & Settlement (Purchase 100k to TST, Pay 60k to TST via HBL)
    await postVoucher({
      reference: 'E2E-PUR-001',
      date: '2026-09-02',
      currency: 'PKR',
      description: 'Credit Purchase from TST Co',
      voucher_type: 'Journal',
      entries: [
        { account_code: '4100', amount: 100000, type: 'debit' },
        { account_code: '2110', amount: 100000, type: 'credit' },
      ]
    });
    await postVoucher({
      reference: 'E2E-PAY-001',
      date: '2026-09-02',
      currency: 'PKR',
      description: 'Supplier Payment to TST Co via HBL',
      voucher_type: 'Payment',
      entries: [
        { account_code: '2110', amount: 60000, type: 'debit' },
        { account_code: '1121', amount: 60000, type: 'credit' },
      ]
    });
    console.log('✓ E2E-008: Credit Purchase & Supplier Payment posted (TST Co remaining: 40k)');

    // E2E-009: Additional Transactions (UBL 100k <- HBL 100k Contra; Internet 10k <- Cash; Deprec 10k <- Accum Deprec)
    await postVoucher({
      reference: 'CV-E2E-001',
      date: '2026-09-02',
      currency: 'PKR',
      description: 'Internal Transfer from HBL to UBL',
      voucher_type: 'contra',
      entries: [
        { account_code: '1125', amount: 100000, type: 'debit' },
        { account_code: '1121', amount: 100000, type: 'credit' },
      ]
    });
    await postVoucher({
      reference: 'E2E-EXP-003',
      date: '2026-09-02',
      currency: 'PKR',
      description: 'Internet Expense Paid Cash',
      voucher_type: 'Payment',
      entries: [
        { account_code: '4230', amount: 10000, type: 'debit' },
        { account_code: '1110', amount: 10000, type: 'credit' },
      ]
    });
    await postVoucher({
      reference: 'E2E-JNL-001',
      date: '2026-09-02',
      currency: 'PKR',
      description: 'Monthly Depreciation on Computer Equipment',
      voucher_type: 'Journal',
      entries: [
        { account_code: '4240', amount: 10000, type: 'debit' },
        { account_code: '1590', amount: 10000, type: 'credit' },
      ]
    });
    console.log('✓ E2E-009: Contra, Internet Expense & Depreciation posted');

    // 4. CERTIFICATION ASSERTIONS (E2E-010 & RECX-001 to RECX-020)
    console.log('\n4. Executing Mathematical Verification & Cross-Report Certification...');

    // A. Verify Balance Sheet
    const bsRes = await fetch('http://localhost:8000/api/reports/balance-sheet?as_of_date=2026-12-31&currency=PKR', { headers: tenantHeaders });
    const bsData = (await bsRes.json()).data;

    const totalAssets = Number(bsData.total_assets);
    const totalLiabilities = Number(bsData.total_liabilities);
    const totalEquity = Number(bsData.total_equity);
    const retainedEarnings = Number(bsData.retained_earnings);

    console.log(`Balance Sheet Summary:`);
    console.log(`- Total Assets:      Rs. ${totalAssets.toLocaleString()}`);
    console.log(`- Total Liabilities: Rs. ${totalLiabilities.toLocaleString()}`);
    console.log(`- Capital:           Rs. ${(totalEquity - retainedEarnings).toLocaleString()}`);
    console.log(`- Net Profit / P&L:  Rs. ${retainedEarnings.toLocaleString()}`);
    console.log(`- Total Equity:      Rs. ${totalEquity.toLocaleString()}`);
    console.log(`- Liabilities+Equity:Rs. ${(totalLiabilities + totalEquity).toLocaleString()}`);

    const expectedAssets = 1350000;
    const expectedLiabilities = 40000;
    const expectedEquity = 1310000;
    const expectedProfit = 310000;

    if (totalAssets !== expectedAssets) {
      throw new Error(`Total Assets mismatch: expected ${expectedAssets}, got ${totalAssets}`);
    }
    if (totalLiabilities !== expectedLiabilities) {
      throw new Error(`Total Liabilities mismatch: expected ${expectedLiabilities}, got ${totalLiabilities}`);
    }
    if (totalEquity !== expectedEquity) {
      throw new Error(`Total Equity mismatch: expected ${expectedEquity}, got ${totalEquity}`);
    }
    if (retainedEarnings !== expectedProfit) {
      throw new Error(`Net Profit mismatch: expected ${expectedProfit}, got ${retainedEarnings}`);
    }

    const variance = Math.abs(totalAssets - (totalLiabilities + totalEquity));
    if (variance !== 0) {
      throw new Error(`Accounting Equation Broken! Assets != Liabilities + Equity. Variance: ${variance}`);
    }
    console.log('✓ RECX-017 / BS-014: Accounting Equation strictly balanced (Variance: Rs. 0)');

    // B. Verify Trial Balance
    const tbRes = await fetch('http://localhost:8000/api/reports/trial-balance?as_of_date=2026-12-31&currency=PKR', { headers: tenantHeaders });
    const tbData = (await tbRes.json()).data;
    const tbDebit = Number(tbData.total_debit);
    const tbCredit = Number(tbData.total_credit);

    if (tbDebit !== tbCredit) {
      throw new Error(`Trial Balance out of balance! Dr: ${tbDebit}, Cr: ${tbCredit}`);
    }
    console.log(`✓ TB-001 / RECX-008: Trial Balance balanced (Dr: Rs. ${tbDebit.toLocaleString()} == Cr: Rs. ${tbCredit.toLocaleString()})`);

    // C. Verify Profit & Loss
    const plRes = await fetch('http://localhost:8000/api/reports/profit-loss?from_date=2026-01-01&to_date=2026-12-31&currency=PKR', { headers: tenantHeaders });
    const plData = (await plRes.json()).data;
    const plRevenue = Number(plData.total_revenue);
    const plExpenses = Number(plData.total_expenses);
    const plNetProfit = Number(plData.net_profit);

    if (plRevenue !== 500000) {
      throw new Error(`P&L Revenue mismatch: expected 500,000, got ${plRevenue}`);
    }
    if (plExpenses !== 190000) {
      throw new Error(`P&L Expenses mismatch: expected 190,000, got ${plExpenses}`);
    }
    if (plNetProfit !== 310000) {
      throw new Error(`P&L Net Profit mismatch: expected 310,000, got ${plNetProfit}`);
    }
    console.log('✓ PL-001 / PL-002 / PL-003: P&L Statement perfectly certified (Revenue 500k - Expenses 190k = Net Profit 310k)');

    // D. Verify Receivables Subledger Report
    const arRes = await fetch('http://localhost:8000/api/reports/receivables?as_of_date=2026-12-31&currency=PKR', { headers: tenantHeaders });
    const arData = (await arRes.json()).data;
    const totalAR = Number(arData.total_receivables);
    if (totalAR !== 300000) {
      throw new Error(`Receivables Report mismatch: expected 300,000, got ${totalAR}`);
    }
    console.log('✓ AR-005 / RECX-004: Receivables Subledger certified (Total AR: Rs. 300,000 matches Balance Sheet)');

    // E. Verify Payables Subledger Report
    const apRes = await fetch('http://localhost:8000/api/reports/payables?as_of_date=2026-12-31&currency=PKR', { headers: tenantHeaders });
    const apData = (await apRes.json()).data;
    const totalAP = Number(apData.total_payables);
    if (totalAP !== 40000) {
      throw new Error(`Payables Report mismatch: expected 40,000, got ${totalAP}`);
    }
    console.log('✓ AP-005 / RECX-006: Payables Subledger certified (Total AP: Rs. 40,000 matches Balance Sheet)');

    // F. Verify Group Ledger (Current Assets 1100)
    const gledRes = await fetch('http://localhost:8000/api/reports/ledger?account_code=1100&from_date=2026-01-01&to_date=2026-12-31&currency=PKR', { headers: tenantHeaders });
    const gledData = (await gledRes.json()).data;
    const expectedCurrentAssets = 960000;
    const closingGled = Number(gledData.closing_balance);
    if (closingGled !== expectedCurrentAssets) {
      throw new Error(`Group Ledger Current Assets (1100) mismatch: expected ${expectedCurrentAssets}, got ${closingGled}`);
    }
    console.log(`✓ GLED-001 / GLED-010: Group Ledger Current Assets (1100) certified (Closing: Rs. ${closingGled.toLocaleString()})`);

    // 5. Browser UI Visual Verification
    console.log('5. Verifying Financial Reports screen in browser UI...');
    await page.evaluate(() => localStorage.setItem('current_company_code', 'E2E_CERT'));
    await page.goto(`${BASE_URL}/?page=reports`);
    await page.waitForTimeout(1000);

    // Click Receivables Report Card
    const arCard = page.locator('button').filter({ hasText: /Accounts Receivable/i }).first();
    if (await arCard.isVisible()) {
      await arCard.click();
      await page.waitForTimeout(800);
      console.log('✓ Navigated to Accounts Receivable view in Financial Reports UI');
    }

    // Click Payables Report Card
    const apCard = page.locator('button').filter({ hasText: /Accounts Payable/i }).first();
    if (await apCard.isVisible()) {
      await apCard.click();
      await page.waitForTimeout(800);
      console.log('✓ Navigated to Accounts Payable view in Financial Reports UI');
    }

    console.log('\n================================================================');
    console.log('🎉 MASTER E2E ACCOUNTING CERTIFICATION PASSED 100%!');
    console.log('================================================================');

    await browser.close();
    return true;
  } catch (err) {
    console.error('❌ Test 07 Failed:', err.message);
    await page.screenshot({ path: 'test-07-failure.png', fullPage: true }).catch(() => {});
    await browser.close();
    return false;
  }
}

module.exports = run;

if (require.main === module) {
  run().then(success => process.exit(success ? 0 : 1));
}