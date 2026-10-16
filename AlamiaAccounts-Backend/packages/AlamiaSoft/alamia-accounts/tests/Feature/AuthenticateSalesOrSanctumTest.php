<?php

namespace AlamiaSoft\AlamiaAccounts\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthenticateSalesOrSanctumTest extends TestCase
{
    use RefreshDatabase;

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
        $secret = 'test-pos-secret-with-sufficient-entropy-alpha';
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

        $this->withHeader('X-POS-Key', 'not-configured-key')
            ->getJson('/_test/pos-auth-context')
            ->assertUnauthorized()
            ->assertJsonPath('error', 'invalid_pos_credential');
    }

    public function test_pos_secret_cannot_select_another_company(): void
    {
        $secret = 'test-pos-secret-with-sufficient-entropy-beta';
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

    public function test_revoked_credential_fails_authentication(): void
    {
        $oldSecret = 'revoked-pos-secret-phase-1';
        $newSecret = 'active-pos-secret-phase-2';

        // Only new secret hash is configured (simulating complete revocation of oldSecret)
        config()->set('alamia-accounts.pos_api_key_hashes', [
            hash('sha256', $newSecret) => 'KAMAL_EXPRESS',
        ]);

        // Old revoked secret must fail with 401
        $this->withHeader('X-POS-Key', $oldSecret)
            ->getJson('/_test/pos-auth-context')
            ->assertUnauthorized()
            ->assertJsonPath('error', 'invalid_pos_credential');

        // New secret succeeds
        $this->withHeader('X-POS-Key', $newSecret)
            ->getJson('/_test/pos-auth-context')
            ->assertOk()
            ->assertJson([
                'company_code' => 'KAMAL_EXPRESS',
                'auth_mode' => 'pos_gateway',
            ]);
    }

    public function test_dual_key_rotation_window_permits_both_until_old_is_revoked(): void
    {
        $oldSecret = 'rotation-window-old-key';
        $newSecret = 'rotation-window-new-key';

        // During cutover window, both hashes are configured
        config()->set('alamia-accounts.pos_api_key_hashes', [
            hash('sha256', $oldSecret) => 'KAMAL_EXPRESS',
            hash('sha256', $newSecret) => 'KAMAL_EXPRESS',
        ]);

        // Both authenticate cleanly
        $this->withHeader('X-POS-Key', $oldSecret)
            ->getJson('/_test/pos-auth-context')
            ->assertOk();

        $this->withHeader('X-POS-Key', $newSecret)
            ->getJson('/_test/pos-auth-context')
            ->assertOk();

        // Cutover complete: revoke old key
        config()->set('alamia-accounts.pos_api_key_hashes', [
            hash('sha256', $newSecret) => 'KAMAL_EXPRESS',
        ]);

        $this->withHeader('X-POS-Key', $oldSecret)
            ->getJson('/_test/pos-auth-context')
            ->assertUnauthorized();

        $this->withHeader('X-POS-Key', $newSecret)
            ->getJson('/_test/pos-auth-context')
            ->assertOk();
    }

    public function test_bearer_authorization_header_authenticates_pos_hash(): void
    {
        $secret = 'test-pos-secret-bearer-token';
        config()->set('alamia-accounts.pos_api_key_hashes', [
            hash('sha256', $secret) => 'KAMAL_EXPRESS',
        ]);

        $this->withToken($secret)
            ->getJson('/_test/pos-auth-context')
            ->assertOk()
            ->assertJson([
                'company_code' => 'KAMAL_EXPRESS',
                'auth_mode' => 'pos_gateway',
            ]);
    }

    public function test_credentials_and_hashes_never_leaked_in_error_payloads(): void
    {
        config()->set('alamia-accounts.pos_api_key_hashes', []);

        $secretAttempt = 'super-secret-pos-value-never-to-be-leaked';
        $response = $this->withHeader('X-POS-Key', $secretAttempt)
            ->getJson('/_test/pos-auth-context')
            ->assertUnauthorized();

        $content = $response->getContent();
        $this->assertStringNotContainsString($secretAttempt, $content);
        $this->assertStringNotContainsString(hash('sha256', $secretAttempt), $content);
    }

    public function test_credentials_for_company_a_cannot_access_company_b(): void
    {
        $secretA = 'secret-for-company-a';
        $secretB = 'secret-for-company-b';

        config()->set('alamia-accounts.pos_api_key_hashes', [
            hash('sha256', $secretA) => 'MAIN',
            hash('sha256', $secretB) => 'KAMAL_EXPRESS',
        ]);

        // Key A spoofing Company B
        $this->withHeaders([
            'X-POS-Key' => $secretA,
            'X-Company-Code' => 'KAMAL_EXPRESS',
        ])->getJson('/_test/pos-auth-context')
            ->assertForbidden()
            ->assertJsonPath('error', 'company_access_denied');

        // Key B spoofing Company A
        $this->withHeaders([
            'X-POS-Key' => $secretB,
            'X-Company-Code' => 'MAIN',
        ])->getJson('/_test/pos-auth-context')
            ->assertForbidden()
            ->assertJsonPath('error', 'company_access_denied');
    }

    public function test_invalid_hash_formats_in_configuration_are_ignored_and_fail_closed(): void
    {
        // Malformed non-64-hex key in configuration
        config()->set('alamia-accounts.pos_api_key_hashes', [
            'not-a-valid-sha256-hash' => 'KAMAL_EXPRESS',
            '123' => 'MAIN',
        ]);

        $this->withHeader('X-POS-Key', 'not-a-valid-sha256-hash')
            ->getJson('/_test/pos-auth-context')
            ->assertUnauthorized()
            ->assertJsonPath('error', 'invalid_pos_credential');
    }
}
