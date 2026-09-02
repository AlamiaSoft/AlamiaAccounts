/**
 * Master E2E Test Suite Runner for Alamia Accounts.
 * Executes all modular test files sequentially and outputs a consolidated test report.
 */
const test01 = require('./01-auth-navigation.test');
const test02 = require('./02-chart-of-accounts.test');
const test03 = require('./03-financial-reports.test');
const test04 = require('./04-multi-company-isolation.test');

async function runAll() {
  console.log('================================================================');
  console.log('       ALAMIA ACCOUNTS - AUTOMATED E2E TEST RUNNER              ');
  console.log('================================================================\n');

  const startTime = Date.now();
  const tests = [
    { name: '01: Authentication & Navigation', fn: test01 },
    { name: '02: Chart of Accounts & Hierarchy', fn: test02 },
    { name: '03: Financial Reports & Mathematical Balances', fn: test03 },
    { name: '04: Multi-Company Isolation & Switching', fn: test04 },
  ];

  const results = [];

  for (const test of tests) {
    const testStart = Date.now();
    try {
      const passed = await test.fn();
      results.push({
        name: test.name,
        status: passed ? 'PASSED' : 'FAILED',
        duration: `${((Date.now() - testStart) / 1000).toFixed(2)}s`,
      });
    } catch (err) {
      results.push({
        name: test.name,
        status: 'FAILED',
        error: err.message,
        duration: `${((Date.now() - testStart) / 1000).toFixed(2)}s`,
      });
    }
  }

  const totalDuration = ((Date.now() - startTime) / 1000).toFixed(2);

  console.log('================================================================');
  console.log('                      TEST EXECUTION SUMMARY                    ');
  console.log('================================================================');
  console.table(results);

  const allPassed = results.every(r => r.status === 'PASSED');
  if (allPassed) {
    console.log(`\n🎉 ALL TESTS PASSED in ${totalDuration}s!`);
    process.exit(0);
  } else {
    console.error(`\n❌ SOME TESTS FAILED in ${totalDuration}s. Review logs above.`);
    process.exit(1);
  }
}

runAll();
