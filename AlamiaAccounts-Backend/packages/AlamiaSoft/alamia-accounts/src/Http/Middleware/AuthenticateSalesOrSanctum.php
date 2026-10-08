<?php

namespace AlamiaSoft\AlamiaAccounts\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Laravel\Sanctum\PersonalAccessToken;

class AuthenticateSalesOrSanctum
{
    /**
     * Authorized POS Gateway API Keys mapped to tenant companies.
     */
    protected static array $posApiKeys = [
        'demo_kamal_token' => 'KAMAL_EXPRESS',
        'kamal_pos_live_key_9921' => 'KAMAL_EXPRESS',
        'demo_pos_token' => 'MAIN',
        'alamia_pos_key_001' => 'MAIN',
    ];

    /**
     * Handle an incoming request through the hybrid enterprise security pipeline.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestedCompany = strtoupper(trim(
            $request->input('company_code') ??
            $request->header('X-Company-Code') ??
            ''
        ));

        // 1. Attempt standard Laravel Sanctum authentication
        $user = auth('sanctum')->user();

        // Fallback: If Sanctum guard didn't auto-resolve, check PersonalAccessToken table directly
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

            // Enforce tenant boundary for restricted staff users
            if (!$isAdminOrManager && !empty($user->company_code) && strtoupper($user->company_code) !== $requestedCompany) {
                return response()->json([
                    'success' => false,
                    'message' => "Access denied. User is assigned to company {$user->company_code}, not {$requestedCompany}.",
                    'error' => "Access denied. User is assigned to company {$user->company_code}, not {$requestedCompany}.",
                ], 403);
            }

            $effectiveCompany = $requestedCompany ?: ($user->company_code ?? 'MAIN');

            // Attach clean authentication context to request
            $request->setUserResolver(fn() => $user);
            $request->attributes->set('tenant_company_code', $effectiveCompany);
            $request->attributes->set('auth_mode', 'sanctum_user');
            $request->attributes->set('is_manager', $isAdminOrManager);

            return $next($request);
        }

        // 2. Attempt Machine-to-Machine POS Gateway Key authentication
        $posKey = $request->header('X-POS-Key') ?? $request->bearerToken() ?? $request->input('api_token');

        if (empty($posKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required: Missing Bearer Token or POS API Key in Authorization header.',
                'error' => 'Authentication required: Missing Bearer Token or POS API Key in Authorization header.',
            ], 401);
        }

        $authorizedCompany = static::$posApiKeys[$posKey] ?? null;

        if (!$authorizedCompany) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication failed: Invalid or unrecognized POS Gateway API key.',
                'error' => 'Authentication failed: Invalid or unrecognized POS Gateway API key.',
            ], 401);
        }

        // Enforce cross-tenant spoofing prevention
        if (!empty($requestedCompany) && $requestedCompany !== $authorizedCompany) {
            return response()->json([
                'success' => false,
                'message' => "Cross-tenant access forbidden. Provided API key is strictly bound to [{$authorizedCompany}], but request specified [{$requestedCompany}].",
                'error' => "Cross-tenant access forbidden. Provided API key is strictly bound to [{$authorizedCompany}], but request specified [{$requestedCompany}].",
            ], 403);
        }

        // Attach clean POS Gateway context to request
        $request->attributes->set('tenant_company_code', $authorizedCompany);
        $request->attributes->set('auth_mode', 'pos_gateway');
        $request->attributes->set('is_manager', false);

        return $next($request);
    }
}
