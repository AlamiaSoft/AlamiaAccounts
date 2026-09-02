<?php

namespace AlamiaSoft\AlamiaAccounts\Http\Controllers\Api;

use AlamiaSoft\AlamiaAccounts\Http\Controllers\Controller;
use Illuminate\Http\Request;
use AlamiaSoft\AlamiaAccounts\Services\CompanyService;
use AlamiaSoft\AlamiaAccounts\Services\DomainContext;
use Abivia\Ledger\Models\LedgerDomain;

/**
 * @OA\Tag(name="Companies", description="Operations related to Company management")
 */
class CompanyController extends Controller
{
    protected $companyService;

    public function __construct(CompanyService $companyService)
    {
        $this->companyService = $companyService;
    }

    /**
     * @OA\Get(
     *     path="/companies",
     *     summary="Get all companies",
     *     tags={"Companies"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="string", example="COMP1"),
     *                     @OA\Property(property="name", type="string", example="Company One"),
     *                     @OA\Property(property="industry", type="string", example="General"),
     *                     @OA\Property(property="currency", type="string", example="USD")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index()
    {
        $companies = $this->companyService->listCompanies();
        
        return response()->json([
            'data' => $companies->map(function($company) {
                $extra = is_string($company->extra) ? json_decode($company->extra, true) : $company->extra;
                $name = $extra['name'] ?? ($company->code === 'MAIN' ? 'Main Company' : $company->code);
                return [
                    'id' => $company->code,
                    'code' => $company->code,
                    'name' => $name,
                    'industry' => $extra['industry'] ?? 'General',
                    'currency' => $company->currencyDefault ?? 'PKR',
                ];
            })
        ]);
    }

    /**
     * @OA\Get(
     *     path="/companies/{code}",
     *     summary="Get company details",
     *     tags={"Companies"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="code", in="path", required=true, @OA\Schema(type="string", example="COMP1")),
     *     @OA\Response(response=200, description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="string", example="COMP1"),
     *                 @OA\Property(property="name", type="string", example="Company One"),
     *                 @OA\Property(property="industry", type="string", example="General"),
     *                 @OA\Property(property="currency", type="string", example="USD")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Company not found")
     * )
     */
    public function show($code)
    {
        $company = $this->companyService->getDomain($code);
        
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $extra = is_string($company->extra) ? json_decode($company->extra, true) : $company->extra;
        $name = $extra['name'] ?? ($company->code === 'MAIN' ? 'Main Company' : $company->code);
        
        return response()->json([
            'data' => [
                'id' => $company->code,
                'code' => $company->code,
                'name' => $name,
                'industry' => $extra['industry'] ?? 'General',
                'currency' => $company->currencyDefault ?? 'PKR',
            ]
        ]);
    }

    /**
     * @OA\Post(
     *     path="/companies",
     *     summary="Create a new company",
     *     tags={"Companies"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "code", "industry", "currency"},
     *             @OA\Property(property="name", type="string", example="New Company Name"),
     *             @OA\Property(property="code", type="string", example="NEWCO"),
     *             @OA\Property(property="industry", type="string", example="Technology"),
     *             @OA\Property(property="currency", type="string", example="USD")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Company created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="string", example="NEWCO"),
     *                 @OA\Property(property="name", type="string", example="New Company Name"),
     *                 @OA\Property(property="industry", type="string", example="Technology"),
     *                 @OA\Property(property="currency", type="string", example="USD")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Failed to create company")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:32|unique:ledger_domains,code',
            'industry' => 'required|string',
            'currency' => 'required|string|size:3',
        ]);

        try {
            $domain = $this->companyService->createCompany(
                $validated['code'],
                $validated['name'],
                [
                    'currency' => $validated['currency'],
                    'industry' => $validated['industry'],
                ]
            );
            
            return response()->json([
                'data' => [
                    'id' => $domain->code,
                    'code' => $domain->code,
                    'name' => $validated['name'],
                    'industry' => $validated['industry'],
                    'currency' => $domain->currencyDefault ?? 'PKR',
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create company',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/companies/{code}",
     *     summary="Update an existing company",
     *     tags={"Companies"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="code", in="path", required=true, @OA\Schema(type="string", example="COMP1")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "industry"},
     *             @OA\Property(property="name", type="string", example="Updated Company Name"),
     *             @OA\Property(property="industry", type="string", example="Finance")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Company updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="string", example="COMP1"),
     *                 @OA\Property(property="name", type="string", example="Updated Company Name"),
     *                 @OA\Property(property="industry", type="string", example="Finance"),
     *                 @OA\Property(property="currency", type="string", example="USD")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Company not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, $code)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'industry' => 'required|string',
        ]);

        $company = $this->companyService->getDomain($code);
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $extra = json_decode($company->extra, true);
        $extra['name'] = $validated['name'];
        $extra['industry'] = $validated['industry'];
        $company->extra = json_encode($extra);
        $company->save();

        return response()->json([
            'data' => [
                'id' => $company->code,
                'name' => $extra['name'] ?? null,
                'industry' => $extra['industry'] ?? 'General',
                'currency' => $company->currencyDefault,
            ]
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/companies/{code}",
     *     summary="Delete a company",
     *     tags={"Companies"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="code", in="path", required=true, @OA\Schema(type="string", example="COMP1")),
     *     @OA\Response(response=204, description="Company deleted successfully"),
     *     @OA\Response(response=404, description="Company not found")
     * )
     */
    public function destroy($code)
    {
        $company = $this->companyService->getDomain($code);
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }
        $this->companyService->deleteDomain($code);
        
        return response()->json(null, 204);
    }

    /**
     * @OA\Post(
     *     path="/companies/{code}/switch",
     *     summary="Switch current company context",
     *     tags={"Companies"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="code", in="path", required=true, @OA\Schema(type="string", example="COMP1")),
     *     @OA\Response(response=200, description="Company context switched",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Company switched successfully"),
     *             @OA\Property(property="current_company", type="string", example="COMP1")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Company not found")
     * )
     */
    public function switch(Request $request, $code)
    {
        $company = $this->companyService->getDomain($code);
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }
        DomainContext::set($code);
        
        return response()->json([
            'message' => 'Company switched successfully',
            'current_company' => $code
        ]);
    }

    public function current()
    {
        $currentCode = DomainContext::get();
        // If no context is set, we might want to return the first company or handle it.
        // But DomainContext::get() usually returns something or throws?
        // Let's assume it returns a code.
        
        if (!$currentCode) {
             // Fallback to first company if no context
             $first = $this->companyService->listCompanies()->first();
             if ($first) {
                 $currentCode = $first->code;
                 DomainContext::set($currentCode);
             } else {
                 return response()->json(['message' => 'No companies found'], 404);
             }
        }

        $company = $this->companyService->getDomain($currentCode);
        
        if (!$company) {
            $first = $this->companyService->listCompanies()->first();
            if ($first) {
                $currentCode = $first->code;
                DomainContext::set($currentCode);
                $company = $first;
            } else {
                return response()->json(['message' => 'No companies found'], 404);
            }
        }
        
        $extra = is_string($company->extra) ? json_decode($company->extra, true) : $company->extra;
        $name = $extra['name'] ?? ($company->code === 'MAIN' ? 'Main Company' : $company->code);
        
        return response()->json([
            'data' => [
                'id' => $company->code,
                'code' => $company->code,
                'name' => $name,
                'industry' => $extra['industry'] ?? 'General',
                'currency' => $company->currencyDefault ?? 'PKR',
            ]
        ]);
    }
}
