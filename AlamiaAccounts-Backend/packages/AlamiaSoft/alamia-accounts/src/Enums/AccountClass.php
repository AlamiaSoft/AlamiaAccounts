<?php

namespace AlamiaSoft\AlamiaAccounts\Enums;

/**
 * Standard Double-Entry Accounting Account Classes.
 * Defines the 5 fundamental accounting elements and their debit/credit norms.
 */
enum AccountClass: string
{
    case ASSET = 'asset';
    case LIABILITY = 'liability';
    case EQUITY = 'equity';
    case REVENUE = 'revenue';
    case EXPENSE = 'expense';
    case UNCLASSIFIED = 'unclassified';

    /**
     * Whether this account belongs to the Balance Sheet.
     */
    public function isBalanceSheet(): bool
    {
        return match($this) {
            self::ASSET, self::LIABILITY, self::EQUITY => true,
            self::REVENUE, self::EXPENSE, self::UNCLASSIFIED => false,
        };
    }

    /**
     * Whether this account belongs to the Profit & Loss (Income Statement).
     */
    public function isProfitAndLoss(): bool
    {
        return match($this) {
            self::REVENUE, self::EXPENSE => true,
            self::ASSET, self::LIABILITY, self::EQUITY, self::UNCLASSIFIED => false,
        };
    }

    /**
     * Expected normal balance side ('debit' or 'credit').
     */
    public function normalBalance(): string
    {
        return match($this) {
            self::ASSET, self::EXPENSE => 'debit',
            self::LIABILITY, self::EQUITY, self::REVENUE => 'credit',
            self::UNCLASSIFIED => 'debit',
        };
    }

    /**
     * True if this class has a normal debit balance.
     */
    public function isDebitNormal(): bool
    {
        return $this->normalBalance() === 'debit';
    }

    /**
     * True if this class has a normal credit balance.
     */
    public function isCreditNormal(): bool
    {
        return $this->normalBalance() === 'credit';
    }

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match($this) {
            self::ASSET => 'Asset',
            self::LIABILITY => 'Liability',
            self::EQUITY => 'Capital',
            self::REVENUE => 'Income',
            self::EXPENSE => 'Expense',
            self::UNCLASSIFIED => 'Unclassified',
        };
    }
}
