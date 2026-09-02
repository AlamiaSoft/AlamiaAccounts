<?php

namespace AlamiaSoft\AlamiaAccounts\Http\Controllers\Api;

use AlamiaSoft\AlamiaAccounts\Http\Controllers\Controller;
use Illuminate\Http\Request;
use AlamiaSoft\AlamiaAccounts\Services\SearchService;
use AlamiaSoft\AlamiaAccounts\Services\DomainContext;

/**
 * @OA\Tag(name="Search", description="Search operations")
 */
class SearchController extends Controller
{
    protected $searchService;

    public function __construct(SearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    /**
     * @OA\Get(
     *     path="/search",
     *     summary="Global search common entities",
     *     tags={"Search"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="query", in="query", required=true, @OA\Schema(type="string", minLength=2)),
     *     @OA\Parameter(name="company_code", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */

    public function search(Request $request)
    {
        $validated = $request->validate([
            'query' => 'required|string|min:2',
            'company_code' => 'nullable|string',
        ]);

        $query = $validated['query'];
        $companyCode = $validated['company_code'] ?? DomainContext::get();

        try {
            $results = $this->searchService->globalSearch($query, $companyCode);
            
            // Add user search (app-specific)
            $users = $this->searchUsers($query);
            $results['users'] = $users;
            
            return response()->json([
                'data' => $results,
                'query' => $query,
                'company_code' => $companyCode
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/search/vouchers",
     *     summary="Search vouchers",
     *     tags={"Search"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="query", in="query", required=true, @OA\Schema(type="string", minLength=2)),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */

    /**
     * Search vouchers only
     */
    public function searchVouchers(Request $request)
    {
        $validated = $request->validate([
            'query' => 'required|string|min:2',
            'company_code' => 'nullable|string',
        ]);

        $query = $validated['query'];
        $companyCode = $validated['company_code'] ?? DomainContext::get();

        try {
            $vouchers = $this->searchService->searchVouchers($query, $companyCode);
            
            return response()->json([
                'data' => $vouchers
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search accounts only
     */
    public function searchAccounts(Request $request)
    {
        $validated = $request->validate([
            'query' => 'required|string|min:2',
            'company_code' => 'nullable|string',
        ]);

        $query = $validated['query'];
        $companyCode = $validated['company_code'] ?? DomainContext::get();

        try {
            $accounts = $this->searchService->searchAccounts($query, $companyCode);
            
            return response()->json([
                'data' => $accounts
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search ledger entries only
     */
    public function searchLedgerEntries(Request $request)
    {
        $validated = $request->validate([
            'query' => 'required|string|min:2',
            'company_code' => 'nullable|string',
        ]);

        $query = $validated['query'];
        $companyCode = $validated['company_code'] ?? DomainContext::get();

        try {
            $entries = $this->searchService->searchLedgerEntries($query, $companyCode);
            
            return response()->json([
                'data' => $entries
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search users (app-specific)
     */
    private function searchUsers(string $query): array
    {
        // This is app-specific user search
        // In production, this would query the users table
        return \DB::table('users')
            ->where(function($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('email', 'like', "%{$query}%");
            })
            ->limit(50)
            ->get()
            ->toArray();
    }
}
