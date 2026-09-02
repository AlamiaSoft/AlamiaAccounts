<?php

namespace AlamiaSoft\AlamiaAccounts\Http\Controllers\Api;

use AlamiaSoft\AlamiaAccounts\Http\Controllers\Controller;
use AlamiaSoft\AlamiaAccounts\Models\AccountingAuditTrail;
use AlamiaSoft\AlamiaAccounts\Services\DomainContext;
use Illuminate\Http\Request;

class AuditTrailController extends Controller
{
    public function index(Request $request)
    {
        $domain = DomainContext::getDomain();
        if (!$domain) {
            return response()->json(['message' => 'Company domain not found.'], 404);
        }

        $query = AccountingAuditTrail::where('domain_uuid', $domain->domainUuid)
            ->orderBy('created_at', 'desc');

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        if ($request->filled('entity_type')) {
            $query->where('entity_type', $request->input('entity_type'));
        }

        $logs = $query->limit($request->input('limit', 100))->get();

        return response()->json(['data' => $logs]);
    }
}
