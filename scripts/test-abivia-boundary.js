'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const test = require('node:test');

const { scanAbiviaBoundary } = require('./abivia-boundary');

function withFixture(files, callback) {
    const root = fs.mkdtempSync(path.join(os.tmpdir(), 'alamia-abivia-boundary-'));
    try {
        for (const [relativePath, content] of Object.entries(files)) {
            const absolutePath = path.join(root, relativePath);
            fs.mkdirSync(path.dirname(absolutePath), { recursive: true });
            fs.writeFileSync(absolutePath, content);
        }
        callback(root);
    } finally {
        fs.rmSync(root, { recursive: true, force: true });
    }
}

function scanFixture(root) {
    return scanAbiviaBoundary(root, {
        roots: ['AlamiaAccounts-Backend'],
        approvedProductionFiles: new Set(['AlamiaAccounts-Backend/src/Services/ApprovedLedgerService.php']),
        approvedAdapterTestPrefixes: ['AlamiaAccounts-Backend/tests/Adapters/Abivia/'],
    });
}

test('rejects direct Abivia model imports in consumer production code', () => {
    withFixture({
        'AlamiaAccounts-Backend/app/Example.php': String.raw`<?php
use Abivia\Ledger\Models\LedgerDomain;
`,
    }, (root) => {
        const result = scanFixture(root);
        assert.equal(result.violations.length, 1);
        assert.equal(result.violations[0].file, 'AlamiaAccounts-Backend/app/Example.php');
        assert.equal(result.violations[0].line, 2);
    });
});

test('rejects fully qualified Abivia service calls in ordinary feature tests', () => {
    withFixture({
        'AlamiaAccounts-Backend/packages/example/tests/Feature/ExampleTest.php': String.raw`<?php
$domain = \Abivia\Ledger\Models\LedgerDomain::where('code', 'MAIN')->first();
`,
    }, (root) => {
        const result = scanFixture(root);
        assert.equal(result.violations.length, 1);
        assert.match(result.violations[0].message, /ordinary tests/);
    });
});

test('rejects Abivia service, facade, repository and controller namespaces too', () => {
    withFixture({
        'AlamiaAccounts-Backend/app/Example.php': String.raw`<?php
use Abivia\Ledger\Http\Controllers\JournalEntryController;
use Abivia\Ledger\Services\LedgerService;
use Abivia\Ledger\Facades\Ledger;
use Abivia\Ledger\Repositories\LedgerRepository;
`,
    }, (root) => {
        assert.equal(scanFixture(root).violations.length, 4);
    });
});

test('allows only an explicitly approved internal integration file', () => {
    withFixture({
        'AlamiaAccounts-Backend/src/Services/ApprovedLedgerService.php': String.raw`<?php
use Abivia\Ledger\Models\LedgerDomain;
`,
    }, (root) => {
        const result = scanFixture(root);
        assert.equal(result.violations.length, 0);
        assert.equal(result.approvedReferences.length, 1);
    });
});

test('allows a dedicated Abivia adapter test, not a normal feature test', () => {
    withFixture({
        'AlamiaAccounts-Backend/tests/Adapters/Abivia/LedgerAdapterTest.php': String.raw`<?php
use Abivia\Ledger\Models\LedgerDomain;
`,
        'AlamiaAccounts-Backend/tests/Feature/PublicVoucherTest.php': String.raw`<?php
use Abivia\Ledger\Models\LedgerDomain;
`,
    }, (root) => {
        const result = scanFixture(root);
        assert.equal(result.approvedReferences.length, 1);
        assert.equal(result.violations.length, 1);
        assert.match(result.violations[0].file, /PublicVoucherTest\.php$/);
    });
});

test('ignores comments so prose does not trigger a false positive', () => {
    withFixture({
        'AlamiaAccounts-Backend/app/Example.php': String.raw`<?php
// Do not call Abivia\Ledger\Models\LedgerDomain here.
# Abivia\Ledger\Services\Something is also forbidden in code.
`,
    }, (root) => {
        assert.equal(scanFixture(root).violations.length, 0);
    });
});
