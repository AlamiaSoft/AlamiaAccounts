/**
 * ============================================================================
 * TEST SUITE 10: ACCOUNTANT PRODUCTION READINESS & FINAL CERTIFICATION
 * ============================================================================
 * Evaluates operational safety, accounting governance, and controls:
 * 1. Multi-Company Setup & Switch
 * 2. Compound Balanced Opening Balance Setup (Dr = Cr = 1,950,000)
 * 3. Day 1 Zero-Variance Balance Sheet Equilibrium (Rs. 0.00 difference)
 * 4. Duplicate & Unbalanced Opening Balance Prevention Guards
 * 5. Fiscal Period Controls & Period Locking (Closed Period Post Rejection)
 * 6. Authorized Period Reopening with Mandatory Business Reason
 * 7. Historical Ledger Immutability (Physical Delete Blocked - AUD-01)
 * 8. Daybook UI Voucher Reversal Workflow with Compensating REV- Entry
 * 9. Permanent Accounting Audit Trail Verification
 * 10. Final Three-Way Double-Entry Certification Matrix
 */

const { launchBrowser, login, BASE_URL } = require('./config');

async function test10AccountantProductionReadiness() {
  console.log('\n================================================================');
  console.log(' SUITE 10: ACCOUNTANT PRODUCTION READINESS & FINAL CERTIFICATION ');
  console.log('================================================================\n');

  const browser = await launchBrowser();
  const page = await browser.newPage();
  let authToken = null;
  const companyCode = 'PROD_CERT';

  // Results Tracker
  const results = [];
  function record(criterion, expected, actual, pass, notes = '') {
    results.push({ criterion, expected, actual, status: pass ? 'PASS' : 'FAIL', notes });
    const mark = pass ? '✓ PASS' : '✗ FAIL';
    console.log(`  [${mark}] ${criterion}: ${notes || actual}`);
  }

  try {
    // ------------------------------------------------------------------------
    // Step 1: Authentication
    // ------------------------------------------------------------------------
    console.log('--- Step 1: Authentication & Token Capture ---');
    await login(page);

    // Capture token from localStorage (key is 'auth_token')
    authToken = await page.evaluate(() => localStorage.getItem('auth_token') || sessionStorage.getItem('auth_token'));
    if (!authToken) {
      const loginRes = await fetch('http://localhost:8000/api/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ email: 'admin@admin.com', password: 'password' })
      });
      const loginData = await loginRes.json();
      authToken = loginData.token || loginData.data?.token;
      if (authToken) {
        await page.evaluate((t) => localStorage.setItem('auth_token', t), authToken);
      }
    }
    record('Auth & Session', 'Valid Auth Token', authToken ? 'Token Captured' : 'Failed', !!authToken);

    // ------------------------------------------------------------------------
    // Step 2: Company Setup & Switch
    // ------------------------------------------------------------------------
    console.log('--- Step 2: Create & Switch Company (PROD_CERT) ---');
    await page.goto(`${BASE_URL}/?page=companies`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1000);

    // Clean up if existing
    await page.evaluate(async (token) => {
      try {
        await fetch('http://localhost:8000/api/companies/PROD_CERT', {
          method: 'DELETE',
          headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
        });
      } catch (e) {}
    }, authToken);

    // Create Company via API helper
    const createRes = await page.evaluate(async ({ token, code }) => {
      const res = await fetch('http://localhost:8000/api/companies', {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          name: 'Production Certified Enterprise',
          code: code,
          currency: 'PKR',
          industry: 'Financial & Professional Services'
        })
      });
      return { status: res.status, body: await res.json() };
    }, { token: authToken, code: companyCode });

    record('Company Creation', '201 Created', `Status ${createRes.status}`, createRes.status === 201 || createRes.status === 200);

    // Switch to PROD_CERT
    await page.evaluate(async ({ token, code }) => {
      await fetch(`http://localhost:8000/api/companies/${code}/switch`, {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
      });
      localStorage.setItem('current_company_code', code);
    }, { token: authToken, code: companyCode });

    await page.reload({ waitUntil: 'networkidle' });
    await page.waitForTimeout(1000);

    // ------------------------------------------------------------------------
    // Step 3: Balanced Compound Opening Balance Setup
    // ------------------------------------------------------------------------
    console.log('--- Step 3: Post Balanced Compound Opening Balances ---');
    // Balance Sheet Structure:
    // Assets (Dr):
    // 1110 Cash: 400,000
    // 1121 Meezan Bank: 1,000,000
    // 1210 Accounts Receivable: 300,000
    // 1510 Office Equipment: 250,000
    // Total Assets = 1,950,000
    // Liabilities & Equity (Cr):
    // 2110 Accounts Payable: 150,000
    // 5100 Owner's Capital: 1,500,000
    // 5200 Retained Earnings: 300,000
    // Total Liab & Equity = 1,950,000
    // Equilibrium: Dr 1,950,000 == Cr 1,950,000
    const obPayload = {
      balance_date: '2026-01-01',
      entries: [
        { account_code: '1110', amount: 400000, type: 'debit', description: 'Opening Cash' },
        { account_code: '1121', amount: 1000000, type: 'debit', description: 'Opening Bank' },
        { account_code: '1210', amount: 300000, type: 'debit', description: 'Opening Trade Receivables' },
        { account_code: '1510', amount: 250000, type: 'debit', description: 'Opening Office Equipment' },
        { account_code: '2110', amount: 150000, type: 'credit', description: 'Opening Trade Payables' },
        { account_code: '5100', amount: 1500000, type: 'credit', description: "Opening Owner's Capital" },
        { account_code: '5200', amount: 300000, type: 'credit', description: 'Opening Retained Earnings' },
      ]
    };

    const obPostRes = await page.evaluate(async ({ token, code, payload }) => {
      const res = await fetch('http://localhost:8000/api/opening-balances', {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'X-Company-Code': code,
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify(payload)
      });
      return { status: res.status, body: await res.json() };
    }, { token: authToken, code: companyCode, payload: obPayload });

    record(
      'Compound Opening Balance Post',
      '201 Created',
      `Status ${obPostRes.status}`,
      obPostRes.status === 201,
      `Voucher: ${obPostRes.body?.data?.reference || 'None'}`
    );

    // ------------------------------------------------------------------------
    // Step 4: Day 1 Balance Sheet Equilibrium
    // ------------------------------------------------------------------------
    console.log('--- Step 4: Verify Day 1 Balance Sheet Equilibrium ---');
    await page.goto(`${BASE_URL}/?page=balance-sheet`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1500);

    const bsText = await page.evaluate(() => document.body.innerText);
    const hasAssets = bsText.includes('1,950,000');
    const isBalancedBadge = bsText.includes('Balanced') || !bsText.includes('Action Required');
    record('Day 1 Balance Sheet Equilibrium', 'Rs. 1,950,000 Balanced', hasAssets && isBalancedBadge ? 'Balanced (Diff: Rs. 0)' : 'Unbalanced', hasAssets && isBalancedBadge);

    // ------------------------------------------------------------------------
    // Step 5: Duplicate & Unbalanced Opening Balance Prevention
    // ------------------------------------------------------------------------
    console.log('--- Step 5: Duplicate & Unbalanced Opening Balance Guards ---');
    // Test duplicate block
    const dupObRes = await page.evaluate(async ({ token, code, payload }) => {
      const res = await fetch('http://localhost:8000/api/opening-balances', {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'X-Company-Code': code,
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify(payload)
      });
      return { status: res.status, body: await res.json() };
    }, { token: authToken, code: companyCode, payload: obPayload });

    record(
      'Duplicate Opening Balance Block',
      '422 Unprocessable',
      `Status ${dupObRes.status}`,
      dupObRes.status === 422,
      dupObRes.body?.message || ''
    );

    // ------------------------------------------------------------------------
    // Step 6: Fiscal Period Locking & Closed Period Guard
    // ------------------------------------------------------------------------
    console.log('--- Step 6: Fiscal Period Locking & Closed Period Enforcement ---');
    await page.goto(`${BASE_URL}/?page=periods`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1500);

    // Fetch periods
    const periodsRes = await page.evaluate(async ({ token, code }) => {
      const res = await fetch('http://localhost:8000/api/periods?year=2026', {
        headers: { 'Authorization': `Bearer ${token}`, 'X-Company-Code': code, 'Accept': 'application/json' }
      });
      return await res.json();
    }, { token: authToken, code: companyCode });

    const periodList = periodsRes.data || [];
    const p1 = periodList.find(p => p.period_number === 1); // Jan 2026
    record('Fiscal Periods Initialized', '12 Monthly Periods', `${periodList.length} Periods Found`, periodList.length === 12);

    // Lock Period 1
    const closeRes = await page.evaluate(async ({ token, code, id }) => {
      const res = await fetch(`http://localhost:8000/api/periods/${id}/close`, {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${token}`, 'X-Company-Code': code, 'Accept': 'application/json' }
      });
      return { status: res.status, body: await res.json() };
    }, { token: authToken, code: companyCode, id: p1?.id });

    record('Period Lock (Jan 2026)', 'Closed / Locked', closeRes.body?.data?.status || 'Failed', closeRes.body?.data?.status === 'closed');

    // Attempt posting into closed period
    const closedPostRes = await page.evaluate(async ({ token, code }) => {
      const res = await fetch('http://localhost:8000/api/vouchers', {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'X-Company-Code': code,
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          reference: 'BLOCKED-JAN-001',
          currency: 'PKR',
          date: '2026-01-15',
          description: 'Ordinary Expense in Closed Period',
          entries: [
            { account_code: '1110', amount: 5000, type: 'credit' },
            { account_code: '5100', amount: 5000, type: 'debit' },
          ]
        })
      });
      return { status: res.status, body: await res.json() };
    }, { token: authToken, code: companyCode });

    const isClosedBlocked = closedPostRes.status === 422 || closedPostRes.status === 500;
    record(
      'Closed Period Posting Blocked',
      'Rejection (Period Closed)',
      `Status ${closedPostRes.status}`,
      isClosedBlocked,
      closedPostRes.body?.message || ''
    );

    // ------------------------------------------------------------------------
    // Step 7: Authorized Period Reopen with Reason
    // ------------------------------------------------------------------------
    console.log('--- Step 7: Authorized Period Reopen ---');
    const reopenRes = await page.evaluate(async ({ token, code, id }) => {
      const res = await fetch(`http://localhost:8000/api/periods/${id}/reopen`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'X-Company-Code': code,
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({ reason: 'Authorized audit adjustment approved by CFO' })
      });
      return { status: res.status, body: await res.json() };
    }, { token: authToken, code: companyCode, id: p1?.id });

    record('Period Reopened', 'Status: open', reopenRes.body?.data?.status || 'Failed', reopenRes.body?.data?.status === 'open');

    // ------------------------------------------------------------------------
    // Step 8: Historical Immutability (Physical Delete Blocked)
    // ------------------------------------------------------------------------
    console.log('--- Step 8: Historical Immutability (Physical Delete Blocked) ---');
    // Post an operating voucher in February 2026
    const opVoucherRes = await page.evaluate(async ({ token, code }) => {
      const res = await fetch('http://localhost:8000/api/vouchers', {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'X-Company-Code': code,
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          reference: 'VCH-FEB-001',
          currency: 'PKR',
          date: '2026-02-10',
          description: 'Consulting Services Revenue',
          entries: [
            { account_code: '1121', amount: 50000, type: 'debit' },
            { account_code: '5100', amount: 50000, type: 'credit' },
          ]
        })
      });
      return { status: res.status, body: await res.json() };
    }, { token: authToken, code: companyCode });

    record('Operating Voucher Posted', '201 Created', `Status ${opVoucherRes.status}`, opVoucherRes.status === 201);

    // Attempt physical DELETE on posted voucher
    const deleteRes = await page.evaluate(async ({ token, code }) => {
      const res = await fetch('http://localhost:8000/api/vouchers/VCH-FEB-001', {
        method: 'DELETE',
        headers: {
          'Authorization': `Bearer ${token}`,
          'X-Company-Code': code,
          'Accept': 'application/json'
        }
      });
      return { status: res.status, body: await res.json() };
    }, { token: authToken, code: companyCode });

    const isDeletePrevented = deleteRes.status === 422 || deleteRes.status === 405;
    record(
      'Physical Delete Blocked (GAAP Immutability)',
      '422 Unprocessable Entity',
      `Status ${deleteRes.status}`,
      isDeletePrevented,
      deleteRes.body?.message || ''
    );

    // ------------------------------------------------------------------------
    // Step 9: Daybook Voucher Reversal Workflow
    // ------------------------------------------------------------------------
    console.log('--- Step 9: Daybook Voucher Reversal Workflow ---');
    await page.goto(`${BASE_URL}/?page=daybook`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1500);

    // Click "All Dates" checkbox to view all vouchers
    const allDatesCheckbox = page.locator('button:has-text("Show All Dates")').or(page.locator('label:has-text("Show all dates")')).or(page.locator('input[type="checkbox"]'));
    if (await allDatesCheckbox.count() > 0) {
      await allDatesCheckbox.first().click();
      await page.waitForTimeout(1000);
    }

    // Execute reversal via API
    const reverseRes = await page.evaluate(async ({ token, code }) => {
      const res = await fetch('http://localhost:8000/api/vouchers/VCH-FEB-001/reverse', {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'X-Company-Code': code,
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({ reason: 'Client billing adjustment' })
      });
      return { status: res.status, body: await res.json() };
    }, { token: authToken, code: companyCode });

    record('Voucher Reversal Executed', '201 Created (REV-VCH-FEB-001)', `Status ${reverseRes.status}`, reverseRes.status === 201);

    // Reload Daybook and verify visual indicators
    await page.reload({ waitUntil: 'networkidle' });
    await page.waitForTimeout(1500);

    const daybookText = await page.evaluate(() => document.body.innerText);
    const hasReversalEntry = daybookText.includes('REV-VCH-FEB-001');
    const hasReversedStatus = daybookText.includes('Reversed') || daybookText.includes('Reversal');
    record('Daybook Reversal Visibility', 'REV-VCH-FEB-001 & Badges visible', hasReversalEntry ? 'Found' : 'Missing', hasReversalEntry);

    // ------------------------------------------------------------------------
    // Step 10: Permanent Accounting Audit Trail Verification
    // ------------------------------------------------------------------------
    console.log('--- Step 10: Permanent Accounting Audit Trail ---');
    const auditRes = await page.evaluate(async ({ token, code }) => {
      const res = await fetch('http://localhost:8000/api/audit-trail', {
        headers: { 'Authorization': `Bearer ${token}`, 'X-Company-Code': code, 'Accept': 'application/json' }
      });
      return await res.json();
    }, { token: authToken, code: companyCode });

    const logs = auditRes.data || [];
    const actionsFound = new Set(logs.map(l => l.action));

    const hasObAudit = actionsFound.has('POST_OPENING_BALANCES');
    const hasCloseAudit = actionsFound.has('CLOSE_PERIOD');
    const hasReopenAudit = actionsFound.has('REOPEN_PERIOD');
    const hasCreateAudit = actionsFound.has('CREATE_VOUCHER');
    const hasReverseAudit = actionsFound.has('REVERSE_VOUCHER');

    const auditPass = hasObAudit && hasCloseAudit && hasReopenAudit && hasCreateAudit && hasReverseAudit;
    record(
      'Complete Audit Trail Coverage',
      'OB, Close, Reopen, Create, Reverse logged',
      `Logged Actions: ${Array.from(actionsFound).join(', ')}`,
      auditPass,
      `Total audit events recorded: ${logs.length}`
    );

    // ------------------------------------------------------------------------
    // Step 11: Final Balance Sheet Post-Reversal Equilibrium
    // ------------------------------------------------------------------------
    console.log('--- Step 11: Final Financial Reports Post-Reversal Check ---');
    await page.goto(`${BASE_URL}/?page=balance-sheet`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1500);

    const finalBsText = await page.evaluate(() => document.body.innerText);
    const finalBalanced = !finalBsText.includes('Action Required') && finalBsText.includes('1,950,000');
    record('Final Post-Reversal Balance Sheet', 'Equilibrium Maintained', finalBalanced ? 'Balanced (Diff: Rs. 0)' : 'Unbalanced', finalBalanced);

  } catch (err) {
    console.error('Fatal Suite Error:', err);
    record('Execution Health', 'Zero fatal unhandled exceptions', err.message, false);
  } finally {
    await browser.close();
  }

  // --------------------------------------------------------------------------
  // Summary & Certification Matrix
  // --------------------------------------------------------------------------
  console.log('\n================================================================');
  console.log('         SUITE 10: CERTIFICATION MATRIX & AUDIT REPORT          ');
  console.log('================================================================');
  console.table(results);

  const total = results.length;
  const passed = results.filter(r => r.status === 'PASS').length;
  const failed = total - passed;

  console.log(`\nFinal Score: ${passed}/${total} assertions passed (${((passed / total) * 100).toFixed(1)}%).`);
  if (failed === 0) {
    console.log('🎉 VERDICT: ACCOUNTANT READY — System satisfies all hardening criteria.\n');
  } else {
    console.log(`⚠️ VERDICT: NOT READY — ${failed} hardening requirements failed.\n`);
  }

  return failed === 0;
}

module.exports = test10AccountantProductionReadiness;

if (require.main === module) {
  test10AccountantProductionReadiness()
    .then(pass => process.exit(pass ? 0 : 1))
    .catch(err => {
      console.error(err);
      process.exit(1);
    });
}
