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

        \App\Models\User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Ali',
                'password' => bcrypt('password'),
            ]
        );

        $this->call([
            \AlamiaSoft\AlamiaAccounts\Database\Seeders\LedgerInitializationSeeder::class,
            // \AlamiaSoft\AlamiaAccounts\Database\Seeders\ChartOfAccountsSeeder::class,
        ]);
    }
}
