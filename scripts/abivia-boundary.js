'use strict';

const fs = require('node:fs');
const path = require('node:path');

const SOURCE_EXTENSIONS = new Set(['.php', '.js', '.jsx', '.ts', '.tsx', '.mjs', '.cjs']);
const EXCLUDED_DIRS = new Set(['.git', 'node_modules', 'vendor', 'storage', '.next', 'dist', 'build', 'coverage', 'cache']);

// These are the existing Alamia-owned ledger/domain services that form the
// current internal integration boundary. This is intentionally a file-level
// allowlist, not a directory-wide exemption. New files must use these services
// or receive explicit architecture review before being added here.
const DEFAULT_APPROVED_PRODUCTION_FILES = new Set([
    'AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Services/AccountMappingResolver.php',
    'AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Services/AccountService.php',
    'AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Services/AccountingDiagnosticService.php',
    'AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Services/AccountingIntegrityGuard.php',
    'AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Services/CompanyService.php',
    'AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Services/DomainContext.php',
    'AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Services/OpeningBalanceService.php',
    'AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Services/ReportService.php',
    'AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Services/SearchService.php',
    'AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Services/VoucherService.php',
    // These two Alamia-owned persistence models map an internal relationship
    // to the underlying domain model; they do not expose Abivia to consumers.
    'AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Models/AccountingAuditTrail.php',
    'AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Models/AccountingPeriod.php',
    'AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Models/DomainJournalEntry.php',
    'AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Models/DomainLedgerAccount.php',
    'AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Models/OpeningBalanceBatch.php',
    'AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Models/PostingIdempotencyRecord.php',
    'AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Models/UnpostedIntegrationEntry.php',
]);

const DEFAULT_APPROVED_ADAPTER_TEST_PREFIXES = [
    'AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/tests/Adapters/Abivia/',
];

// Deliberately broad enough to catch model, service, facade, repository,
// controller, message, helper and exception namespaces—not just Models.
const ABIVIA_REFERENCE = /\bAbivia\\Ledger\\/i;

function normalizeRelativePath(filePath) {
    return filePath.split(path.sep).join('/');
}

function stripCommentsPreservingLines(source) {
    // Keep newlines and line positions stable so diagnostics point at source.
    let text = source.replace(/\/\*[\s\S]*?\*\//g, (block) => block.replace(/[^\n]/g, ' '));
    text = text.replace(/(^|[^:])\/\/[^\n]*/g, (comment, prefix) => prefix + ' '.repeat(comment.length - prefix.length));
    text = text.replace(/^\s*#.*$/gm, (line) => line.replace(/[^\n]/g, ' '));
    return text;
}

function walkSourceFiles(rootDir, roots) {
    const found = [];
    const visited = new Set();

    function walk(directory) {
        if (!fs.existsSync(directory)) return;
        const realDirectory = path.resolve(directory);
        if (visited.has(realDirectory)) return;
        visited.add(realDirectory);

        for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
            if (entry.isDirectory() && EXCLUDED_DIRS.has(entry.name)) continue;
            const absolutePath = path.join(directory, entry.name);
            if (entry.isDirectory()) {
                walk(absolutePath);
                continue;
            }
            if (!entry.isFile() || !SOURCE_EXTENSIONS.has(path.extname(entry.name))) continue;
            found.push(absolutePath);
        }
    }

    for (const root of roots) walk(path.join(rootDir, root));
    return found;
}

function isApprovedPath(relativePath, approvedProductionFiles, approvedAdapterTestPrefixes) {
    if (approvedProductionFiles.has(relativePath)) return true;
    return approvedAdapterTestPrefixes.some((prefix) => relativePath.startsWith(prefix));
}

function scanAbiviaBoundary(rootDir, options = {}) {
    const roots = options.roots || [
        'AlamiaAccounts-Backend/app',
        'AlamiaAccounts-Backend/bootstrap',
        'AlamiaAccounts-Backend/config',
        'AlamiaAccounts-Backend/routes',
        'AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src',
        'AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/tests',
        'AlamiaAccounts-Backend/tests',
        'AlamiaAccounts-Frontend',
        'scripts',
    ];
    const approvedProductionFiles = options.approvedProductionFiles || DEFAULT_APPROVED_PRODUCTION_FILES;
    const approvedAdapterTestPrefixes = options.approvedAdapterTestPrefixes || DEFAULT_APPROVED_ADAPTER_TEST_PREFIXES;
    const violations = [];
    const approvedReferences = [];
    const files = walkSourceFiles(rootDir, roots);

    for (const absolutePath of files) {
        const relativePath = normalizeRelativePath(path.relative(rootDir, absolutePath));

        // Do not scan the checker itself or its fixtures as application code.
        if (relativePath === 'scripts/abivia-boundary.js' ||
            relativePath === 'scripts/verify-architecture.js' ||
            relativePath === 'scripts/test-abivia-boundary.js') {
            continue;
        }

        const source = stripCommentsPreservingLines(fs.readFileSync(absolutePath, 'utf8'));
        const lines = source.split('\n');
        lines.forEach((line, index) => {
            if (!ABIVIA_REFERENCE.test(line)) return;
            const finding = {
                file: relativePath,
                line: index + 1,
                snippet: line.trim(),
            };

            if (isApprovedPath(relativePath, approvedProductionFiles, approvedAdapterTestPrefixes)) {
                approvedReferences.push(finding);
            } else {
                const isTestFile = /(^|\/)tests?\//i.test(relativePath);
                violations.push({
                    ...finding,
                    message: isTestFile
                        ? 'Direct Abivia package access is prohibited in ordinary tests; use the Alamia Accounts public contract. Only dedicated tests under tests/Adapters/Abivia/ may cross this boundary.'
                        : 'Direct Abivia package access is outside the approved internal integration boundary; use an Alamia-owned service/contract.',
                });
            }
        });
    }

    return { filesScanned: files.length, violations, approvedReferences };
}

function formatAbiviaBoundaryReport(result) {
    const lines = [
        'Abivia architecture boundary: scanned ' + result.filesScanned + ' source files.',
        'Approved internal references: ' + result.approvedReferences.length + '.',
        'Forbidden references: ' + result.violations.length + '.',
    ];

    for (const violation of result.violations) {
        lines.push('  ✖ ' + violation.file + ':' + violation.line + ': ' + violation.message);
        if (violation.snippet) lines.push('      ' + violation.snippet);
    }

    return lines.join('\n');
}

module.exports = {
    DEFAULT_APPROVED_ADAPTER_TEST_PREFIXES,
    DEFAULT_APPROVED_PRODUCTION_FILES,
    formatAbiviaBoundaryReport,
    scanAbiviaBoundary,
};

if (require.main === module) {
    const rootDir = path.resolve(__dirname, '..');
    const result = scanAbiviaBoundary(rootDir);
    console.log(formatAbiviaBoundaryReport(result));
    process.exitCode = result.violations.length === 0 ? 0 : 1;
}
