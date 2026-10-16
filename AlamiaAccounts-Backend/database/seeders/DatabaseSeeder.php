<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $admin = \App\Models\User::firstOrNew(['email' => 'admin@admin.com']);
        $admin->fill([
            'name' => 'Ali',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'status' => 'active',
        ]);
        $admin->company_code = 'MAIN';
        $admin->allowed_companies = ['*'];
        $admin->save();

        $this->call([
            \AlamiaSoft\AlamiaAccounts\Database\Seeders\LedgerInitializationSeeder::class,
            StandardErpKnowledgeSeeder::class,
            KamalExpressPilotSeeder::class,
            KamalExpressCustomVoucherSeeder::class,
        ]);
    }
}
