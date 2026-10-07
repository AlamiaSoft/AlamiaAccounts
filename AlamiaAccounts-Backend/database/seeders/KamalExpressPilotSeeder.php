<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use AlamiaSoft\AlamiaAccounts\Services\CompanyService;
use AlamiaSoft\AlamiaAccounts\Services\AccountService;
use AlamiaSoft\AlamiaAccounts\Services\DomainContext;
use Abivia\Ledger\Models\LedgerDomain;
use Abivia\Ledger\Models\LedgerAccount;

class KamalExpressPilotSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🚀 Seeding Kamal Express Pilot Tenant & Travel COA...');

        $companyCode = 'KAMAL_EXPRESS';
        $companyName = 'Kamal Express Travel & Tours';

        // 1. Ensure Domain Exists
        $companyService = app(CompanyService::class);
        $domain = LedgerDomain::where('code', $companyCode)->first();
        if (!$domain) {
            $domain = $companyService->createCompany($companyCode, $companyName, [
                'industry' => 'Travel & Tourism',
                'currency' => 'PKR',
                'type' => 'company',
            ]);
            $this->command->info("✅ Created Company Domain: {$companyCode}");
        }

        DomainContext::set($companyCode);
        $accountService = app(AccountService::class);

        // 2. Travel Agency Specialized Subledger Accounts
        $specializedAccounts = [
            // Bank Accounts under 1120
            ['code' => '1121', 'name' => 'Meezan Bank - Travel Corporate A/c', 'debit' => true, 'parent_code' => '1120', 'category' => false],
            ['code' => '1122', 'name' => 'Habib Bank Limited (HBL) - Collections', 'debit' => true, 'parent_code' => '1120', 'category' => false],

            // Customer Receivables Subledgers under 1100
            ['code' => '1201', 'name' => 'Corporate Client - Apex Logistics', 'debit' => true, 'parent_code' => '1100', 'category' => false],
            ['code' => '1202', 'name' => 'Corporate Client - Falcon Traders', 'debit' => true, 'parent_code' => '1100', 'category' => false],

            // Airline / BSP Supplier Payables under 2000 / 2100
            ['code' => '2110', 'name' => 'Airline & BSP Payables', 'category' => true, 'credit' => true, 'parent_code' => '2000'],
            ['code' => '2111', 'name' => 'Pakistan International Airlines (PIA)', 'credit' => true, 'parent_code' => '2110', 'category' => false],
            ['code' => '2112', 'name' => 'Emirates Airline Payables', 'credit' => true, 'parent_code' => '2110', 'category' => false],
            ['code' => '2113', 'name' => 'Airblue Airline Payables', 'credit' => true, 'parent_code' => '2110', 'category' => false],
            ['code' => '2114', 'name' => 'Fly Jinnah Payables', 'credit' => true, 'parent_code' => '2110', 'category' => false],
            ['code' => '2115', 'name' => 'Saudi Airlines (Saudia) Payables', 'credit' => true, 'parent_code' => '2110', 'category' => false],
            ['code' => '2120', 'name' => 'Umrah & Hotel Vendors Payables', 'credit' => true, 'parent_code' => '2000', 'category' => false],

            // Revenue Accounts under 5000 (or 4000 depending on root)
            ['code' => '4100', 'name' => 'Air Ticket Sales Revenue', 'credit' => true, 'parent_code' => '5000', 'category' => false],
            ['code' => '4200', 'name' => 'Umrah & Hajj Package Sales', 'credit' => true, 'parent_code' => '5000', 'category' => false],
            ['code' => '4300', 'name' => 'Visa Processing & Service Fees', 'credit' => true, 'parent_code' => '5000', 'category' => false],
            ['code' => '4400', 'name' => 'Hotel & Tour Booking Commissions', 'credit' => true, 'parent_code' => '5000', 'category' => false],

            // Direct Costs / COGS under 4000 Expenses
            ['code' => '5100', 'name' => 'Airline Ticket Procurement Direct Costs', 'debit' => true, 'parent_code' => '4000', 'category' => false],
            ['code' => '5200', 'name' => 'Hotel & Ground Transport Costs', 'debit' => true, 'parent_code' => '4000', 'category' => false],
            ['code' => '5300', 'name' => 'Visa Processing Fees Paid', 'debit' => true, 'parent_code' => '4000', 'category' => false],
        ];

        foreach ($specializedAccounts as $acc) {
            $existing = LedgerAccount::where('code', $acc['code'])->first();
            if (!$existing) {
                try {
                    $accountService->createAccount($acc);
                    $this->command->info("  + Created account [{$acc['code']}] {$acc['name']}");
                } catch (\Throwable $e) {
                    $this->command->warn("  ! Note on account [{$acc['code']}]: " . $e->getMessage());
                }
            }
        }

        $this->command->info("🎉 Kamal Express Pilot Onboarding & COA Seeding Completed!");
    }
}
