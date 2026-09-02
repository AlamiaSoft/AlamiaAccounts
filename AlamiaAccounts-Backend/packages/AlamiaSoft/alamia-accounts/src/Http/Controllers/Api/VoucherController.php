<?php

namespace AlamiaSoft\AlamiaAccounts\Http\Controllers\Api;

use AlamiaSoft\AlamiaAccounts\Http\Controllers\Controller;
use Illuminate\Http\Request;
use AlamiaSoft\AlamiaAccounts\Services\VoucherService;

/**
 * @OA\Tag(name="Vouchers", description="Operations related to Journal Vouchers")
 */
class VoucherController extends Controller
{
    protected $voucherService;

    public function __construct(VoucherService $voucherService)
    {
        $this->voucherService = $voucherService;
    }

    /**
     * @OA\Get(
     *     path="/vouchers",
     *     summary="Get all vouchers",
     *     tags={"Vouchers"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="from_date", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="to_date", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */

    public function index(Request $request)
    {
        // Get vouchers with optional filtering
        $vouchers = $this->voucherService->getVouchers(
            $request->input('from_date'),
            $request->input('to_date')
        );
        
        return response()->json(['data' => $vouchers]);
    }

    /**
     * @OA\Post(
     *     path="/vouchers",
     *     summary="Create a new journal voucher",
     *     tags={"Vouchers"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"date", "reference", "currency", "entries"},
     *             @OA\Property(property="date", type="string", format="date", example="2023-10-01"),
     *             @OA\Property(property="reference", type="string", example="JV-001"),
     *             @OA\Property(property="description", type="string", example="Monthly adjustment"),
     *             @OA\Property(property="currency", type="string", example="USD"),
     *             @OA\Property(property="entries", type="array", @OA\Items(
     *                 @OA\Property(property="account_code", type="string", example="1000"),
     *                 @OA\Property(property="amount", type="number", example=100.50),
     *                 @OA\Property(property="type", type="string", enum={"debit", "credit"}),
     *                 @OA\Property(property="description", type="string", example="Entry description")
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=201, description="Voucher created successfully")
     * )
     */

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'reference' => 'required|string',
            'description' => 'nullable|string',
            'currency' => 'required|string|size:3',
            'entries' => 'required|array|min:2',
            'entries.*.account_code' => 'required|string',
            'entries.*.amount' => 'required|numeric|min:0',
            'entries.*.type' => 'required|in:debit,credit',
            'entries.*.description' => 'nullable|string',
        ]);

        try {
            $voucher = $this->voucherService->createJournalEntry([
                'date' => $validated['date'],
                'reference' => $validated['reference'],
                'description' => $validated['description'] ?? '',
                'currency' => $validated['currency'],
                'entries' => $validated['entries'],
            ]);

            return response()->json(['data' => $voucher], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * @OA\Get(
     *     path="/vouchers/{reference}",
     *     summary="Get voucher details",
     *     tags={"Vouchers"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="reference", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=404, description="Voucher not found")
     * )
     */

    public function show($reference)
    {
        $voucher = $this->voucherService->getVoucher($reference);
        
        return response()->json(['data' => $voucher]);
    }

    /**
     * @OA\Delete(
     *     path="/vouchers/{reference}",
     *     summary="Delete a voucher",
     *     tags={"Vouchers"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="reference", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=204, description="Voucher deleted successfully"),
     *     @OA\Response(response=404, description="Voucher not found")
     * )
     */

    public function destroy($reference)
    {
        return response()->json([
            'message' => 'Posted accounting vouchers cannot be physically deleted. Use voucher reversal to maintain double-entry audit history.'
        ], 422);
    }

    /**
     * Reverse a posted voucher (creates compensating REV- voucher)
     */
    public function reverse($reference, Request $request)
    {
        try {
            $date = $request->input('date');
            $reason = $request->input('reason');
            $reversed = $this->voucherService->reverseVoucher($reference, $date, $reason);

            return response()->json([
                'message' => "Voucher {$reference} reversed successfully",
                'data' => $reversed,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
    }
}
