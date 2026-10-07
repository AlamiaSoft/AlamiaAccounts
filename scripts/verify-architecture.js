#!/usr/bin/env node

/**
 * ============================================================================
 * ALAMIA ACCOUNTS — ARCHITECTURAL CONFORMANCE & CODING STANDARDS AUDITOR
 * Discovers and reports architectural violations, Abivia leaks, anti-patterns,
 * hardcoded heuristics, and package boundary breaches.
 * ============================================================================
 */

const fs = require('fs');
const path = require('path');

const ROOT_DIR = path.resolve(__dirname, '..');
const BACKEND_DIR = path.join(ROOT_DIR, 'AlamiaAccounts-Backend');
const FRONTEND_DIR = path.join(ROOT_DIR, 'AlamiaAccounts-Frontend');
const PACKAGE_DIR = path.join(BACKEND_DIR, 'packages', 'AlamiaSoft', 'alamia-accounts');
const PROJECT_DIR = path.join(ROOT_DIR, '.project');
const E2E_DIR = path.join(ROOT_DIR, 'tests', 'e2e');

let totalChecks = 0;
let passedChecks = 0;
let totalViolations = 0;
const violationsList = [];

// ANSI Helpers
const colors = {
    reset: '\x1b[0m',
    green: '\x1b[32m',
    red: '\x1b[31m',
    yellow: '\x1b[33m',
    cyan: '\x1b[36m',
    bold: '\x1b[1m',
    dim: '\x1b[2m',
    magenta: '\x1b[35m'
};

function logHeader(title) {
    console.log(`\n${colors.bold}${colors.cyan}════════════════════════════════════════════════════════════════════════${colors.reset}`);
    console.log(`${colors.bold}${colors.cyan} 🛡️  ${title}${colors.reset}`);
    console.log(`${colors.bold}${colors.cyan}════════════════════════════════════════════════════════════════════════${colors.reset}`);
}

function recordViolation(suite, file, line, message, codeSnippet, remediation) {
    totalViolations++;
    const relFile = path.relative(ROOT_DIR, file);
    violationsList.push({ suite, file: relFile, line, message, codeSnippet, remediation });
    console.log(`  ${colors.red}✖ VIOLATION${colors.reset} [${colors.bold}${suite}${colors.reset}] ${colors.bold}${relFile}:${line}${colors.reset}`);
    console.log(`    ${colors.yellow}Issue:${colors.reset} ${message}`);
    if (codeSnippet) {
        console.log(`    ${colors.dim}Code : ${codeSnippet.trim()}${colors.reset}`);
    }
    if (remediation) {
        console.log(`    ${colors.magenta}Fix  : ${remediation}${colors.reset}`);
    }
    console.log('');
}

function walkDir(dir, filterExts = ['.php', '.ts', '.tsx', '.js'], excludeDirs = ['node_modules', '.git', 'vendor', '.next', 'storage']) {
    let results = [];
    if (!fs.existsSync(dir)) return results;

    const list = fs.readdirSync(dir);
    for (const file of list) {
        if (excludeDirs.includes(file)) continue;
        const filePath = path.join(dir, file);
        const stat = fs.statSync(filePath);
        if (stat.isDirectory()) {
            results = results.concat(walkDir(filePath, filterExts, excludeDirs));
        } else {
            const ext = path.extname(file);
            if (filterExts.includes(ext)) {
                results.push(filePath);
            }
        }
    }
    return results;
}

// ----------------------------------------------------------------------------
// SUITE 1: Abivia Ledger Kernel Encapsulation (AGENTS.md Rule 8 & .agents/rules)
// ----------------------------------------------------------------------------
function checkAbiviaEncapsulation() {
    totalChecks++;
    logHeader('SUITE 1: Abivia Ledger Kernel Encapsulation (AGENTS.md Rule 8)');

    const controllerDirs = [
        path.join(BACKEND_DIR, 'app', 'Http', 'Controllers'),
        path.join(PACKAGE_DIR, 'src', 'Http', 'Controllers')
    ];

    let filesToScan = [];
    for (const cDir of controllerDirs) {
        filesToScan = filesToScan.concat(walkDir(cDir, ['.php']));
    }
    // Also include Frontend files
    filesToScan = filesToScan.concat(walkDir(FRONTEND_DIR, ['.ts', '.tsx', '.js']));

    let violationsInSuite = 0;
    const forbiddenPattern = /(use\s+Abivia\\Ledger\\Models|\\Abivia\\Ledger\\Models\\)/i;

    for (const file of filesToScan) {
        const content = fs.readFileSync(file, 'utf8');
        const lines = content.split('\n');

        lines.forEach((lineText, idx) => {
            if (forbiddenPattern.test(lineText)) {
                violationsInSuite++;
                recordViolation(
                    'Abivia-Encapsulation',
                    file,
                    idx + 1,
                    'Direct access/import of Abivia Ledger Models is forbidden in HTTP Controllers and UI.',
                    lineText,
                    'Delegate all ledger queries and domain operations to Alamia Domain Services (VoucherService, AccountService, CompanyService).'
                );
            }
        });
    }

    if (violationsInSuite === 0) {
        passedChecks++;
        console.log(`  ${colors.green}✔ PASS${colors.reset}: All ${filesToScan.length} controllers and UI files strictly respect Abivia kernel encapsulation.`);
    }
}

// ----------------------------------------------------------------------------
// SUITE 2: Zero Hardcoded Accounts & Zero Heuristic Guessing (AGENTS.md Rule 9)
// ----------------------------------------------------------------------------
function checkZeroHardcodedAccounts() {
    totalChecks++;
    logHeader('SUITE 2: Zero Hardcoded Accounts & Zero Heuristic Guessing (AGENTS.md Rule 9)');

    const filesToScan = walkDir(path.join(FRONTEND_DIR, 'components'), ['.tsx', '.ts'])
        .concat(walkDir(path.join(FRONTEND_DIR, 'app'), ['.tsx', '.ts']))
        .concat(walkDir(path.join(PACKAGE_DIR, 'src', 'Http', 'Controllers'), ['.php']));

    let violationsInSuite = 0;

    const heuristicPatterns = [
        {
            regex: /\b(?:account|code)\.startsWith\(['"][45123]['"]\)/i,
            msg: 'Heuristic startsWith() guessing on account numbers is prohibited.',
            fix: 'Retrieve configured default accounts from the tenant Custom Voucher Type or company settings.'
        },
        {
            regex: /\b(?:includes|indexOf)\(['"](?:commission|expense|revenue|payable)['"]\)\s*(?:&&|\|\||\?|===)/i,
            msg: 'Heuristic name pattern matching to guess posting accounts is prohibited.',
            fix: 'Explicitly prompt the accountant in the UI combobox or read pre-configured voucher rules.'
        }
    ];

    for (const file of filesToScan) {
        if (file.includes('global-search.tsx') || file.includes('account-combobox.tsx')) continue;

        const content = fs.readFileSync(file, 'utf8');
        const lines = content.split('\n');

        lines.forEach((lineText, idx) => {
            for (const pat of heuristicPatterns) {
                if (pat.regex.test(lineText)) {
                    violationsInSuite++;
                    recordViolation('Zero-Heuristic-Guessing', file, idx + 1, pat.msg, lineText, pat.fix);
                }
            }
        });
    }

    if (violationsInSuite === 0) {
        passedChecks++;
        console.log(`  ${colors.green}✔ PASS${colors.reset}: Zero hardcoded account guessing heuristics detected across UI and Controllers.`);
    }
}

// ----------------------------------------------------------------------------
// SUITE 3: Centralized Currency & Company Domain Helpers (AGENTS.md Rule 8)
// ----------------------------------------------------------------------------
function checkCentralizedCurrencyHelpers() {
    totalChecks++;
    logHeader('SUITE 3: Centralized Company & Currency Context (AGENTS.md Rule 8)');

    const controllerDirs = [
        path.join(PACKAGE_DIR, 'src', 'Http', 'Controllers'),
        path.join(BACKEND_DIR, 'app', 'Http', 'Controllers')
    ];

    let filesToScan = [];
    for (const cDir of controllerDirs) {
        filesToScan = filesToScan.concat(walkDir(cDir, ['.php']));
    }

    let violationsInSuite = 0;
    const rawCurrencyQueryPattern = /(?:DB::table|select|from)\(['"](?:ledger_domains|companies)['"]\).*->pluck\(['"]currency['"]\)/i;

    for (const file of filesToScan) {
        const content = fs.readFileSync(file, 'utf8');
        const lines = content.split('\n');

        lines.forEach((lineText, idx) => {
            if (rawCurrencyQueryPattern.test(lineText)) {
                violationsInSuite++;
                recordViolation(
                    'Centralized-Currency',
                    file,
                    idx + 1,
                    'Ad-hoc database lookup for company currency in controller.',
                    lineText,
                    'Use DomainContext::getDefaultCurrency() or CompanyService::getDefaultCurrency().'
                );
            }
        });
    }

    if (violationsInSuite === 0) {
        passedChecks++;
        console.log(`  ${colors.green}✔ PASS${colors.reset}: All company settings and currencies resolve through centralized domain helpers.`);
    }
}

// ----------------------------------------------------------------------------
// SUITE 4: GAAP/IFRS Historical Ledger Immutability (AGENTS.md Rule 4)
// ----------------------------------------------------------------------------
function checkHistoricalImmutability() {
    totalChecks++;
    logHeader('SUITE 4: GAAP/IFRS Historical Ledger Immutability (AGENTS.md Rule 4)');

    const voucherControllerPath = path.join(PACKAGE_DIR, 'src', 'Http', 'Controllers', 'Api', 'VoucherController.php');
    let violationsInSuite = 0;

    if (!fs.existsSync(voucherControllerPath)) {
        violationsInSuite++;
        recordViolation('Historical-Immutability', PACKAGE_DIR, 1, 'VoucherController missing from package path.', null, 'Ensure VoucherController exists in src/Http/Controllers/Api/');
    } else {
        const controllerContent = fs.readFileSync(voucherControllerPath, 'utf8');

        // Verify destroy method blocks physical delete
        if (!controllerContent.includes('422') || !controllerContent.includes('cannot be physically deleted')) {
            violationsInSuite++;
            recordViolation(
                'Historical-Immutability',
                voucherControllerPath,
                1,
                'VoucherController::destroy must strictly return HTTP 422 to preserve double-entry audit history.',
                null,
                'Return HTTP 422 with explanation in VoucherController::destroy().'
            );
        }

        // Verify reverse action exists
        if (!controllerContent.includes('function reverse')) {
            violationsInSuite++;
            recordViolation(
                'Historical-Immutability',
                voucherControllerPath,
                1,
                'Compensating reversal endpoint missing in VoucherController.',
                null,
                'Implement POST /api/vouchers/{ref}/reverse endpoint.'
            );
        }
    }

    if (violationsInSuite === 0) {
        passedChecks++;
        console.log(`  ${colors.green}✔ PASS${colors.reset}: GAAP historical immutability is strictly enforced (destructive deletion blocked with 422, reversal active).`);
    }
}

// ----------------------------------------------------------------------------
// SUITE 5: Reusable Package Structure & Service Provider (ARCHITECTURE.md Section 1)
// ----------------------------------------------------------------------------
function checkReusablePackageStructure() {
    totalChecks++;
    logHeader('SUITE 5: Reusable Package Boundaries (ARCHITECTURE.md Section 1)');

    const expectedServices = [
        'VoucherService.php',
        'AccountService.php',
        'PeriodService.php',
        'OpeningBalanceService.php',
        'ReportService.php',
        'CompanyService.php',
        'SearchService.php',
        'DomainContext.php'
    ];

    let violationsInSuite = 0;
    const servicesDir = path.join(PACKAGE_DIR, 'src', 'Services');

    for (const service of expectedServices) {
        const sPath = path.join(servicesDir, service);
        if (!fs.existsSync(sPath)) {
            violationsInSuite++;
            recordViolation(
                'Package-Boundaries',
                PACKAGE_DIR,
                1,
                `Required core domain service missing in reusable package: src/Services/${service}`,
                null,
                `Ensure ${service} is housed under packages/AlamiaSoft/alamia-accounts/src/Services/`
            );
        }
    }

    const providerPath = path.join(PACKAGE_DIR, 'src', 'AlamiaAccountsServiceProvider.php');
    if (!fs.existsSync(providerPath)) {
        violationsInSuite++;
        recordViolation('Package-Boundaries', PACKAGE_DIR, 1, 'AlamiaAccountsServiceProvider.php missing in package root.', null, 'Ensure package ServiceProvider is defined.');
    }

    // Check for duplicate / shadowed orphaned workspaces (Single Source of Truth)
    const shadowBackendDir = path.join(BACKEND_DIR, 'backend');
    if (fs.existsSync(shadowBackendDir)) {
        violationsInSuite++;
        recordViolation(
            'Package-Boundaries',
            shadowBackendDir,
            1,
            'Orphaned / shadowed duplicate package directory detected at AlamiaAccounts-Backend/backend. Creates developer confusion with active workspace AlamiaAccounts-Backend/packages.',
            'AlamiaAccounts-Backend/backend/packages/AlamiaSoft/alamia-accounts',
            'Perform safe quarantine: (1) Rename shadow folder to "_quarantine_backend", (2) Execute full regression test suite (node tests/e2e/run-all.js), (3) Archive/delete only once verified 100% green.'
        );
    }

    if (violationsInSuite === 0) {
        passedChecks++;
        console.log(`  ${colors.green}✔ PASS${colors.reset}: Reusable package structure and service provider contracts are complete.`);
    }
}

// ----------------------------------------------------------------------------
// SUITE 6: E2E Test Suite Registration & Token Preservation Mandate
// ----------------------------------------------------------------------------
function checkE2ETestRegistration() {
    totalChecks++;
    logHeader('SUITE 6: E2E Test Suite Registry & Token Preservation (AGENTS.md Rule 1)');

    const runAllPath = path.join(E2E_DIR, 'run-all.js');
    let violationsInSuite = 0;

    if (!fs.existsSync(runAllPath)) {
        violationsInSuite++;
        recordViolation('E2E-Registry', E2E_DIR, 1, 'tests/e2e/run-all.js master runner file is missing.', null, 'Create run-all.js runner in tests/e2e/');
    } else {
        const runAllContent = fs.readFileSync(runAllPath, 'utf8');
        const testFiles = fs.readdirSync(E2E_DIR).filter(f => f.endsWith('.test.js'));

        for (const testFile of testFiles) {
            const baseName = testFile.replace(/\.js$/, '');
            if (!runAllContent.includes(testFile) && !runAllContent.includes(baseName)) {
                violationsInSuite++;
                recordViolation(
                    'E2E-Registry',
                    runAllPath,
                    1,
                    `Test suite ${testFile} exists in tests/e2e/ but is not registered in run-all.js runner.`,
                    null,
                    `Add ${testFile} to tests array in tests/e2e/run-all.js`
                );
            }
        }
    }

    if (violationsInSuite === 0) {
        passedChecks++;
        console.log(`  ${colors.green}✔ PASS${colors.reset}: All persistent E2E test suites (Suites 01 to 12) are registered in run-all.js.`);
    }
}

// ----------------------------------------------------------------------------
// SUITE 7: Agent-PM Task DAG Integrity (.project/)
// ----------------------------------------------------------------------------
function checkProjectDAGIntegrity() {
    totalChecks++;
    logHeader('SUITE 7: Agent-PM DAG & State Machine Integrity (.project/)');

    const projectYamlPath = path.join(PROJECT_DIR, 'project.yaml');
    const tasksDir = path.join(PROJECT_DIR, 'tasks');
    let violationsInSuite = 0;

    if (!fs.existsSync(projectYamlPath)) {
        violationsInSuite++;
        recordViolation('DAG-Integrity', PROJECT_DIR, 1, '.project/project.yaml configuration file missing.', null, 'Create project.yaml in .project/');
    }

    if (!fs.existsSync(tasksDir)) {
        violationsInSuite++;
        recordViolation('DAG-Integrity', PROJECT_DIR, 1, '.project/tasks/ directory missing.', null, 'Create tasks/ in .project/');
    } else {
        const taskFiles = fs.readdirSync(tasksDir).filter(f => f.endsWith('.md'));
        const validStates = ['backlog', 'ready', 'claimed', 'in_progress', 'review', 'verified', 'done', 'blocked', 'on_hold', 'paused', 'abandoned'];

        for (const tf of taskFiles) {
            const taskPath = path.join(tasksDir, tf);
            const content = fs.readFileSync(taskPath, 'utf8');
            if (!content.startsWith('---')) {
                violationsInSuite++;
                recordViolation('DAG-Integrity', taskPath, 1, `Task file ${tf} missing valid YAML frontmatter.`, null, 'Add valid YAML frontmatter header to task file.');
                continue;
            }

            const stateMatch = content.match(/status:\s*["']?([a-zA-Z_-]+)["']?/);
            if (!stateMatch || !validStates.includes(stateMatch[1].toLowerCase())) {
                violationsInSuite++;
                recordViolation('DAG-Integrity', taskPath, 1, `Task file ${tf} has invalid lifecycle state: "${stateMatch ? stateMatch[1] : 'none'}".`, null, `Update status to one of: ${validStates.join(', ')}`);
            }
        }
    }

    if (violationsInSuite === 0) {
        passedChecks++;
        console.log(`  ${colors.green}✔ PASS${colors.reset}: Agent-PM project configuration and all task specifications have valid schemas and states.`);
    }
}

// ----------------------------------------------------------------------------
// Main Runner & Summary
// ----------------------------------------------------------------------------
function runAllArchitecturalChecks() {
    console.log(`\n${colors.bold}${colors.green}========================================================================${colors.reset}`);
    console.log(`${colors.bold}${colors.green} 🏛️  ALAMIA ACCOUNTS — ARCHITECTURAL CONFORMANCE AUDITOR${colors.reset}`);
    console.log(`${colors.bold}${colors.green}========================================================================${colors.reset}`);

    checkAbiviaEncapsulation();
    checkZeroHardcodedAccounts();
    checkCentralizedCurrencyHelpers();
    checkHistoricalImmutability();
    checkReusablePackageStructure();
    checkE2ETestRegistration();
    checkProjectDAGIntegrity();

    console.log(`\n${colors.bold}════════════════════════════════════════════════════════════════════════${colors.reset}`);
    console.log(`${colors.bold} AUDIT SUMMARY REPORT${colors.reset}`);
    console.log(`════════════════════════════════════════════════════════════════════════`);
    console.log(`  Suites Evaluated : ${totalChecks}`);
    console.log(`  Suites Passed    : ${passedChecks}/${totalChecks} (${Math.round((passedChecks / totalChecks) * 100)}%)`);
    console.log(`  Total Violations : ${totalViolations}`);

    if (totalViolations > 0) {
        console.log(`\n${colors.bold}${colors.red}❌ FAILED: ${totalViolations} architectural violation(s) / drift(s) detected!${colors.reset}`);
        console.log(`${colors.yellow}Review the highlighted violations above to assign to a refactor agent.${colors.reset}\n`);
        process.exit(1);
    } else {
        console.log(`\n${colors.bold}${colors.green}✅ 100% PASS: Complete Architectural & Coding Standards Conformance!${colors.reset}\n`);
        process.exit(0);
    }
}

runAllArchitecturalChecks();
