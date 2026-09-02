<?php

namespace AlamiaSoft\AlamiaAccounts\Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;


class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Ali',
            'email' => 'admin@admin.com',
            'password' => bcrypt('password'),
        ]);
        $this->call([
            LedgerInitializationSeeder::class,
            //ChartOfAccountsSeeder::class,

        ]);

    }
}
