<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Abivia\Ledger\Models\LedgerAccount;
use AlamiaSoft\AlamiaAccounts\Enums\AccountClass;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Backfills account_class metadata in ledger_accounts.extra for formal classification.
     */
    public function up(): void
    {
        $accounts = LedgerAccount::with('names')->get();

        foreach ($accounts as $account) {
            $extra = $account->extra ?? [];
            if (is_string($extra)) {
                $extra = json_decode($extra, true) ?: [];
            } elseif (is_object($extra)) {
                $extra = (array)$extra;
            }

            if (!empty($extra['account_class'])) {
                continue;
            }

            $code = (string)$account->code;
            $name = strtolower($account->names->first()->name ?? '');

            // Determine formal AccountClass for seed/standard accounts
            $class = null;
            if ($code === '1000' || str_starts_with($code, '1') || str_contains($name, 'asset')) {
                $class = AccountClass::ASSET->value;
            } elseif ($code === '2000' || str_starts_with($code, '2') || str_contains($name, 'liabilit')) {
                $class = AccountClass::LIABILITY->value;
            } elseif ($code === '3000' || str_starts_with($code, '3') || str_contains($name, 'revenue') || str_contains($name, 'income')) {
                $class = AccountClass::REVENUE->value;
            } elseif ($code === '4000' || str_starts_with($code, '4') || str_contains($name, 'expense') || str_contains($name, 'cost')) {
                $class = AccountClass::EXPENSE->value;
            } elseif ($code === '5000' || str_starts_with($code, '5') || str_contains($name, 'equity') || str_contains($name, 'capital')) {
                $class = AccountClass::EQUITY->value;
            } else {
                $class = $account->debit ? AccountClass::ASSET->value : AccountClass::LIABILITY->value;
            }

            $extra['account_class'] = $class;

            DB::table('ledger_accounts')
                ->where('ledgerUuid', $account->ledgerUuid)
                ->update(['extra' => json_encode($extra)]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op: metadata preservation
    }
};
