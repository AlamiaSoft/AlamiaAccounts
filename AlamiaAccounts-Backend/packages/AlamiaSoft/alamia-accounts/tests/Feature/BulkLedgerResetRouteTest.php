<?php

namespace AlamiaSoft\AlamiaAccounts\Tests\Feature;

use Tests\TestCase;

class BulkLedgerResetRouteTest extends TestCase
{
    public function test_bulk_ledger_reset_route_is_not_registered(): void
    {
        $this->postJson('/api/vouchers/clear-all')
            ->assertMethodNotAllowed();
    }
}
