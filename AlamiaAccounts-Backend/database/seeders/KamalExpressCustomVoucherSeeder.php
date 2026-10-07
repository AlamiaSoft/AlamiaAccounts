<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use AlamiaSoft\AlamiaAccounts\Services\CustomVoucherTypeService;

class KamalExpressCustomVoucherSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(CustomVoucherTypeService::class);

        // Check if Airline Ticket Booking already exists
        $existing = DB::table('custom_voucher_types')->where('prefix', 'TKT')->first();
        if ($existing) {
            DB::table('custom_voucher_types')->where('prefix', 'TKT')->update([
                'default_debit_account' => '1110',
                'default_credit_account' => '3100',
            ]);
        } else {
            $service->createVoucherType([
                'name' => 'Airline Ticket Booking',
                'prefix' => 'TKT',
                'company_code' => 'KAMAL_EXPRESS',
                'description' => 'Kamal Express front-office ticket booking voucher with PNR, passenger information, and revenue tracking.',
                'default_debit_account' => '1110',
                'default_credit_account' => '3100',
                'active' => true,
                'custom_fields' => [
                    [
                        'name' => 'Passenger Name',
                        'type' => 'text',
                        'required' => true,
                        'options' => null,
                    ],
                    [
                        'name' => 'Passport or CNIC',
                        'type' => 'text',
                        'required' => true,
                        'options' => null,
                    ],
                    [
                        'name' => 'PNR',
                        'type' => 'text',
                        'required' => true,
                        'options' => null,
                    ],
                    [
                        'name' => 'Airline',
                        'type' => 'dropdown',
                        'required' => true,
                        'options' => ['PIA', 'Saudia', 'Emirates', 'Qatar Airways', 'Airblue', 'Fly Jinnah', 'Gulf Air'],
                    ],
                    [
                        'name' => 'Sector Route',
                        'type' => 'text',
                        'required' => true,
                        'options' => null,
                    ],
                    [
                        'name' => 'Ticket Number',
                        'type' => 'text',
                        'required' => false,
                        'options' => null,
                    ],
                    [
                        'name' => 'Gross Fare',
                        'type' => 'number',
                        'required' => true,
                        'options' => null,
                    ],
                    [
                        'name' => 'Agent Commission',
                        'type' => 'number',
                        'required' => false,
                        'options' => null,
                    ],
                    [
                        'name' => 'Payment Mode',
                        'type' => 'dropdown',
                        'required' => true,
                        'options' => ['Cash', 'Bank Transfer', 'Credit Card', 'Customer Receivable'],
                    ],
                ],
                'account_rules' => [
                    [
                        'side' => 'debit',
                        'account_groups' => ['Cash', 'Bank Accounts', 'Accounts Receivable'],
                    ],
                    [
                        'side' => 'credit',
                        'account_groups' => ['Revenue', 'Fee Income', 'Accounts Payable'],
                    ],
                ],
                'validation_rules' => [
                    [
                        'field_name' => 'Passenger Name',
                        'type' => 'required',
                        'message' => 'Passenger Name is required for ticket booking',
                    ],
                    [
                        'field_name' => 'PNR',
                        'type' => 'required',
                        'message' => 'Valid Airline PNR reference is mandatory',
                    ],
                    [
                        'field_name' => 'Gross Fare',
                        'type' => 'min_value',
                        'value' => '1',
                        'message' => 'Gross Fare must be greater than zero',
                    ],
                ],
                'numbering_scheme' => [
                    'starting_number' => 1,
                    'padding' => 4,
                    'separator' => '-',
                    'include_year' => true,
                    'include_month' => false,
                    'reset_period' => 'yearly',
                ],
            ]);
        }

        // Also seed a School Fees Voucher as standard example
        $sfExisting = DB::table('custom_voucher_types')->where('prefix', 'SF')->first();
        if (!$sfExisting) {
            $service->createVoucherType([
                'name' => 'School Fees Voucher',
                'prefix' => 'SF',
                'company_code' => null, // Global
                'description' => 'Custom voucher for student tuition fee collections.',
                'active' => true,
                'custom_fields' => [
                    [
                        'name' => 'Student ID',
                        'type' => 'text',
                        'required' => true,
                        'options' => null,
                    ],
                    [
                        'name' => 'Class Grade',
                        'type' => 'dropdown',
                        'required' => true,
                        'options' => ['Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6', 'Grade 7', 'Grade 8', 'Matric', 'O-Levels', 'A-Levels'],
                    ],
                    [
                        'name' => 'Academic Term',
                        'type' => 'dropdown',
                        'required' => true,
                        'options' => ['Term 1', 'Term 2', 'Term 3', 'Annual'],
                    ],
                ],
                'account_rules' => [
                    [
                        'side' => 'debit',
                        'account_groups' => ['Cash', 'Bank Accounts'],
                    ],
                    [
                        'side' => 'credit',
                        'account_groups' => ['Fee Income', 'Revenue'],
                    ],
                ],
                'validation_rules' => [
                    [
                        'field_name' => 'Student ID',
                        'type' => 'required',
                        'message' => 'Student ID is mandatory',
                    ],
                ],
                'numbering_scheme' => [
                    'starting_number' => 1,
                    'padding' => 4,
                    'separator' => '-',
                    'include_year' => true,
                    'include_month' => false,
                    'reset_period' => 'yearly',
                ],
            ]);
        }
    }
}
