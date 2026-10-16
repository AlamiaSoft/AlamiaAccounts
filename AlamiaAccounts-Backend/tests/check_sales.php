<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$companyService = app(\AlamiaSoft\AlamiaAccounts\Services\CompanyService::class);
$domains = $companyService->listDomains();
echo "Available Ledger Domains / Companies:\n";
foreach ($domains as $d) {
    echo "- Code: {$d->code} | UUID: {$d->domainUuid}\n";
}

echo "\n--- Operational Sales in Database ---\n";
foreach (\AlamiaSoft\AlamiaAccounts\Models\OperationalSale::all() as $s) {
    echo "#{$s->id} | Company: {$s->company_code} | Status: {$s->status} | Cust: {$s->customer_name} | SV: {$s->sales_voucher_ref} | RV: {$s->receipt_voucher_ref} | Total: PKR {$s->total_amount}\n";
}

echo "\n--- Journal Entries in KAMAL_EXPRESS Domain ---\n";
$domain = $companyService->getDomain('KAMAL_EXPRESS');
if ($domain) {
    \AlamiaSoft\AlamiaAccounts\Services\DomainContext::set('KAMAL_EXPRESS');
    $entries = app(\AlamiaSoft\AlamiaAccounts\Services\VoucherService::class)->getJournalEntries();
    echo "Found " . $entries->count() . " journal entries in KAMAL_EXPRESS:\n";
    foreach ($entries as $e) {
        echo "Entry #{$e->journalEntryId} | Date: {$e->transDate} | Description: {$e->description} | Extra: {$e->extra}\n";
    }
}
