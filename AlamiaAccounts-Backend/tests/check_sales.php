<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$domains = \Abivia\Ledger\Models\LedgerDomain::all();
echo "Available Ledger Domains / Companies:\n";
foreach ($domains as $d) {
    echo "- Code: {$d->code} | UUID: {$d->domainUuid}\n";
}

echo "\n--- Operational Sales in Database ---\n";
foreach (\AlamiaSoft\AlamiaAccounts\Models\OperationalSale::all() as $s) {
    echo "#{$s->id} | Company: {$s->company_code} | Status: {$s->status} | Cust: {$s->customer_name} | SV: {$s->sales_voucher_ref} | RV: {$s->receipt_voucher_ref} | Total: PKR {$s->total_amount}\n";
}

echo "\n--- Journal Entries in KAMAL_EXPRESS Domain ---\n";
$keUuid = \Abivia\Ledger\Models\LedgerDomain::where('code', 'KAMAL_EXPRESS')->value('domainUuid');
if ($keUuid) {
    $entries = \Abivia\Ledger\Models\JournalEntry::where('domainUuid', $keUuid)->get();
    echo "Found " . $entries->count() . " journal entries in KAMAL_EXPRESS:\n";
    foreach ($entries as $e) {
        echo "Entry #{$e->journalEntryId} | Date: {$e->transDate} | Description: {$e->description} | Extra: {$e->extra}\n";
    }
}
