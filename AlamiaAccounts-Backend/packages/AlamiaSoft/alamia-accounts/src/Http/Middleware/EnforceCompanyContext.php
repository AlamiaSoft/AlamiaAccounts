<?php

namespace AlamiaSoft\AlamiaAccounts\Http\Middleware;

use Abivia\Ledger\Models\LedgerDomain;
use AlamiaSoft\AlamiaAccounts\Services\DomainContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceCompanyContext
{
    /**
     * Handle an incoming request and enforce fail-closed company context.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user() ?? auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required.',
                'error' => 'authentication_required',
            ], 401);
        }

        $userCompany = strtoupper(trim((string) ($user->company_code ?? '')));
        if ($userCompany === '') {
            return response()->json([
                'success' => false,
                'message' => 'No company is assigned to this account.',
                'error' => 'user_company_unassigned',
            ], 403);
        }

        $requestedCompany = strtoupper(trim(
            $request->header('X-Company-Code') ??
            $request->input('company_code') ??
            $request->query('company_code') ??
            ''
        ));

        if ($requestedCompany !== '' && $userCompany !== $requestedCompany) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied for the requested company.',
                'error' => 'company_access_denied',
            ], 403);
        }

        $effectiveCompany = $userCompany;
        $domain = LedgerDomain::where('code', $effectiveCompany)->first();
        if (!$domain) {
            return response()->json([
                'success' => false,
                'message' => "Authorized company '{$effectiveCompany}' does not exist.",
                'error' => 'company_not_found',
            ], 404);
        }

        $request->attributes->set('tenant_company_code', $effectiveCompany);
        DomainContext::set($effectiveCompany);

        try {
            return $next($request);
        } finally {
            DomainContext::reset();
        }
    }
}
