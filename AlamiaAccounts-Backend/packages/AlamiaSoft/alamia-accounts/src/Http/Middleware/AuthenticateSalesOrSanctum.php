<?php

namespace AlamiaSoft\AlamiaAccounts\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Laravel\Sanctum\PersonalAccessToken;

class AuthenticateSalesOrSanctum
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestedCompany = strtoupper(trim(
            $request->input('company_code') ??
            $request->header('X-Company-Code') ??
            ''
        ));

        $user = auth('sanctum')->user();
        if (!$user) {
            $bearer = $request->bearerToken();
            if ($bearer && str_contains($bearer, '|')) {
                $tokenModel = PersonalAccessToken::findToken($bearer);
                if ($tokenModel && $tokenModel->tokenable) {
                    $user = $tokenModel->tokenable;
                }
            }
        }

        if ($user) {
            $role = strtolower($user->role ?? 'staff');
            $isAdminOrManager = in_array($role, ['admin', 'owner', 'lead_accountant', 'manager', 'accountant'], true);
            $userCompany = strtoupper(trim((string) ($user->company_code ?? '')));

            // This codebase has no verified multi-company membership resolver here.
            // Role names do not grant cross-company access; require an assigned company.
            if ($userCompany === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'No company is assigned to this account.',
                    'error' => 'user_company_unassigned',
                ], 403);
            }

            if ($requestedCompany !== '' && $userCompany !== $requestedCompany) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied for the requested company.',
                    'error' => 'company_access_denied',
                ], 403);
            }

            $effectiveCompany = $userCompany;

            $request->setUserResolver(fn () => $user);
            $request->attributes->set('tenant_company_code', $effectiveCompany);
            $request->attributes->set('auth_mode', 'sanctum_user');
            $request->attributes->set('is_manager', $isAdminOrManager);
            \AlamiaSoft\AlamiaAccounts\Services\DomainContext::set($effectiveCompany);

            try {
                return $next($request);
            } finally {
                \AlamiaSoft\AlamiaAccounts\Services\DomainContext::reset();
            }
        }

        // Machine credentials are represented by SHA-256 hashes in configuration.
        // No POS secrets are embedded in source code.
        $posKey = $request->header('X-POS-Key') ?? $request->bearerToken();
        if (!is_string($posKey) || trim($posKey) === '') {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required.',
                'error' => 'authentication_required',
            ], 401);
        }

        $providedHash = hash('sha256', trim($posKey));
        $authorizedCompany = null;
        $configuredHashes = config('alamia-accounts.pos_api_key_hashes', []);

        if (is_array($configuredHashes)) {
            foreach ($configuredHashes as $configuredHash => $companyCode) {
                if (!is_string($configuredHash) || !preg_match('/^[a-f0-9]{64}$/i', $configuredHash)) {
                    continue;
                }

                if (is_string($companyCode) && hash_equals(strtolower($configuredHash), $providedHash)) {
                    $authorizedCompany = strtoupper(trim($companyCode));
                    break;
                }
            }
        }

        if (!$authorizedCompany) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication failed.',
                'error' => 'invalid_pos_credential',
            ], 401);
        }

        if ($requestedCompany !== '' && $requestedCompany !== $authorizedCompany) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied for the requested company.',
                'error' => 'company_access_denied',
            ], 403);
        }

        $request->attributes->set('tenant_company_code', $authorizedCompany);
        $request->attributes->set('auth_mode', 'pos_gateway');
        $request->attributes->set('is_manager', false);
        \AlamiaSoft\AlamiaAccounts\Services\DomainContext::set($authorizedCompany);

        try {
            return $next($request);
        } finally {
            \AlamiaSoft\AlamiaAccounts\Services\DomainContext::reset();
        }
    }
}
