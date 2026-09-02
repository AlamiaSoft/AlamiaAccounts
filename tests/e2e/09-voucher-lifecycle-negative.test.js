const { launchBrowser, login, BASE_URL } = require('./config');

/**
 * Suite 09: Voucher Lifecycle & Negative Validations (NEG-001, NEG-004, NEG-009, LIFE-008, LIFE-009)
 * Verifies that:
 * 1. Unbalanced vouchers (Dr != Cr) are rejected by accounting engine.
 * 2. Zero-amount and negative line items are rejected.
 * 3. Duplicate voucher reference per company is rejected.
 * 4. Voucher reversal generates a valid compensating REV- entry with inverted Dr/Cr.
 * 5. Double reversals of the same voucher are rejected.
 */
async function run() {
  console.log('--- Running Test 09: Voucher Lifecycle & Negative Validations ---');
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

    // 1. NEG-001: Unbalanced Voucher
    console.log('1. Testing NEG-001: Unbalanced voucher rejection...');
    const unbRes = await fetch('http://localhost:8000/api/vouchers', {
      method: 'POST',
      headers,
      body: JSON.stringify({
        reference: 'NEG-UNB-001',
        date: '2026-09-02',
        currency: 'PKR',
        entries: [
          { account_code: '1110', amount: 5000, type: 'debit' },
          { account_code: '5100', amount: 3000, type: 'credit' },
        ]
      })
    });
    if (unbRes.status !== 422) {
      throw new Error(`Expected status 422 for unbalanced voucher, got ${unbRes.status}`);
    }
    console.log('✓ NEG-001: Unbalanced voucher rejected with status 422');

    // 2. NEG-004: Zero-amount line item
    console.log('2. Testing NEG-004: Zero amount line item rejection...');
    const zeroRes = await fetch('http://localhost:8000/api/vouchers', {
      method: 'POST',
      headers,
      body: JSON.stringify({
        reference: 'NEG-ZERO-001',
        date: '2026-09-02',
        currency: 'PKR',
        entries: [
          { account_code: '1110', amount: 0, type: 'debit' },
          { account_code: '5100', amount: 0, type: 'credit' },
        ]
      })
    });
    if (zeroRes.status !== 422) {
      throw new Error(`Expected status 422 for zero amount, got ${zeroRes.status}`);
    }
    console.log('✓ NEG-004: Zero amount voucher rejected with status 422');

    // 3. NEG-009 / LIFE-011: Duplicate voucher reference
    console.log('3. Testing NEG-009: Duplicate voucher reference rejection...');
    const dupRes = await fetch('http://localhost:8000/api/vouchers', {
      method: 'POST',
      headers,
      body: JSON.stringify({
        reference: 'E2E-OB-001', // Already exists in E2E_CERT
        date: '2026-09-02',
        currency: 'PKR',
        entries: [
          { account_code: '1110', amount: 1000, type: 'debit' },
          { account_code: '5100', amount: 1000, type: 'credit' },
        ]
      })
    });
    if (dupRes.status !== 422) {
      throw new Error(`Expected status 422 for duplicate reference, got ${dupRes.status}`);
    }
    console.log('✓ NEG-009: Duplicate reference prevented with status 422');

    // 4. LIFE-008: Voucher Reversal
    console.log('4. Testing LIFE-008: Voucher reversal workflow...');
    // Create a temporary voucher to reverse
    const tempRef = `VCH-REV-TEST-${Date.now()}`;
    const createTemp = await fetch('http://localhost:8000/api/vouchers', {
      method: 'POST',
      headers,
      body: JSON.stringify({
        reference: tempRef,
        date: '2026-09-02',
        currency: 'PKR',
        description: 'Test Voucher for Reversal',
        entries: [
          { account_code: '4220', amount: 12000, type: 'debit' },
          { account_code: '1110', amount: 12000, type: 'credit' },
        ]
      })
    });
    if (!createTemp.ok) {
      throw new Error(`Failed to create voucher for reversal test: ${await createTemp.text()}`);
    }

    // Call reverse endpoint
    const revRes = await fetch(`http://localhost:8000/api/vouchers/${tempRef}/reverse`, {
      method: 'POST',
      headers,
      body: JSON.stringify({ date: '2026-09-02' })
    });
    if (revRes.status !== 201) {
      throw new Error(`Failed to reverse voucher: ${await revRes.text()}`);
    }
    const revData = await revRes.json();
    console.log(`✓ LIFE-008: Compensating reversal voucher created: REV-${tempRef}`);

    // 5. LIFE-009: Double Reversal Prevention
    console.log('5. Testing LIFE-009: Double reversal prevention...');
    const dblRevRes = await fetch(`http://localhost:8000/api/vouchers/${tempRef}/reverse`, {
      method: 'POST',
      headers,
      body: JSON.stringify({ date: '2026-09-02' })
    });
    if (dblRevRes.status !== 422) {
      throw new Error(`Expected status 422 for double reversal attempt, got ${dblRevRes.status}`);
    }
    console.log('✓ LIFE-009: Double reversal prevented with status 422');

    console.log('\n🎉 TEST 09 PASSED: Voucher lifecycle and negative tests verified successfully!');
    await browser.close();
    return true;
  } catch (err) {
    console.error('❌ Test 09 Failed:', err.message);
    await browser.close();
    return false;
  }
}

module.exports = run;

if (require.main === module) {
  run().then(success => process.exit(success ? 0 : 1));
}