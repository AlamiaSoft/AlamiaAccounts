<?php

namespace AlamiaSoft\AlamiaAccounts\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthenticateSalesOrSanctumTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('sales.auth')->get('/_test/pos-auth-context', function (Request $request) {
            return response()->json([
                'company_code' => $request->attributes->get('tenant_company_code'),
                'auth_mode' => $request->attributes->get('auth_mode'),
            ]);
        });
    }

    public function test_configured_hashed_pos_secret_resolves_only_its_company(): void
    {
        $secret = 'test-pos-secret-with-sufficient-entropy';
        config()->set('alamia-accounts.pos_api_key_hashes', [
            hash('sha256', $secret) => 'KAMAL_EXPRESS',
        ]);

        $this->withHeader('X-POS-Key', $secret)
            ->getJson('/_test/pos-auth-context')
            ->assertOk()
            ->assertJson([
                'company_code' => 'KAMAL_EXPRESS',
                'auth_mode' => 'pos_gateway',
            ]);
    }

    public function test_unconfigured_pos_secret_is_rejected(): void
    {
        config()->set('alamia-accounts.pos_api_key_hashes', []);

        $this->withHeader('X-POS-Key', 'not-configured')
            ->getJson('/_test/pos-auth-context')
            ->assertUnauthorized()
            ->assertJsonPath('error', 'invalid_pos_credential');
    }

    public function test_pos_secret_cannot_select_another_company(): void
    {
        $secret = 'test-pos-secret-with-sufficient-entropy';
        config()->set('alamia-accounts.pos_api_key_hashes', [
            hash('sha256', $secret) => 'KAMAL_EXPRESS',
        ]);

        $this->withHeaders([
            'X-POS-Key' => $secret,
            'X-Company-Code' => 'OTHER_COMPANY',
        ])->getJson('/_test/pos-auth-context')
            ->assertForbidden()
            ->assertJsonPath('error', 'company_access_denied');
    }

    public function test_missing_pos_secret_is_rejected(): void
    {
        config()->set('alamia-accounts.pos_api_key_hashes', []);

        $this->getJson('/_test/pos-auth-context')
            ->assertUnauthorized()
            ->assertJsonPath('error', 'authentication_required');
    }
}
