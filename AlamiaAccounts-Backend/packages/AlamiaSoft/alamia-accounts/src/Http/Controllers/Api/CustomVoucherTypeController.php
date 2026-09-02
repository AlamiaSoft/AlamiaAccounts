<?php

namespace AlamiaSoft\AlamiaAccounts\Http\Controllers\Api;

use AlamiaSoft\AlamiaAccounts\Http\Controllers\Controller;
use Illuminate\Http\Request;
use AlamiaSoft\AlamiaAccounts\Services\CustomVoucherTypeService;

/**
 * @OA\Tag(name="Voucher Types", description="Operations related to Custom Voucher Types")
 */
class CustomVoucherTypeController extends Controller
{
    protected $voucherTypeService;

    public function __construct(CustomVoucherTypeService $voucherTypeService)
    {
        $this->voucherTypeService = $voucherTypeService;
    }

    /**
     * @OA\Get(
     *     path="/voucher-types",
     *     summary="Get all custom voucher types",
     *     tags={"Voucher Types"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */

    public function index()
    {
        try {
            $types = $this->voucherTypeService->getAllVoucherTypes();
            
            return response()->json([
                'data' => $types
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/voucher-types/{id}",
     *     summary="Get a specific voucher type",
     *     tags={"Voucher Types"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=404, description="Voucher type not found")
     * )
     */

    public function show($id)
    {
        try {
            $type = $this->voucherTypeService->getVoucherType($id);
            
            return response()->json([
                'data' => $type
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * @OA\Post(
     *     path="/voucher-types",
     *     summary="Create a new custom voucher type",
     *     tags={"Voucher Types"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "prefix"},
     *             @OA\Property(property="name", type="string", example="Bank Payment"),
     *             @OA\Property(property="prefix", type="string", example="BPV")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Voucher type created successfully")
     * )
     */

    /**
     * Create a new custom voucher type
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'prefix' => 'required|string|max:10',
            'description' => 'nullable|string',
            'active' => 'boolean',
            'custom_fields' => 'array',
            'custom_fields.*.name' => 'required|string',
            'custom_fields.*.type' => 'required|string',
            'custom_fields.*.required' => 'boolean',
            'custom_fields.*.options' => 'array',
            'account_rules' => 'array',
            'validation_rules' => 'array',
            'auto_calculation_rules' => 'array',
            'default_value_rules' => 'array',
            'approval_rules' => 'array',
        ]);

        try {
            $type = $this->voucherTypeService->createVoucherType($validated);
            
            return response()->json([
                'data' => $type,
                'message' => 'Voucher type created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a voucher type
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'prefix' => 'required|string|max:10',
            'description' => 'nullable|string',
            'active' => 'boolean',
            'custom_fields' => 'array',
            'account_rules' => 'array',
            'validation_rules' => 'array',
            'auto_calculation_rules' => 'array',
            'default_value_rules' => 'array',
            'approval_rules' => 'array',
        ]);

        try {
            $type = $this->voucherTypeService->updateVoucherType($id, $validated);
            
            return response()->json([
                'data' => $type,
                'message' => 'Voucher type updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a voucher type
     */
    public function destroy($id)
    {
        try {
            $this->voucherTypeService->deleteVoucherType($id);
            
            return response()->json([
                'message' => 'Voucher type deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate data against voucher type
     */
    public function validate(Request $request, $id)
    {
        try {
            $errors = $this->voucherTypeService->validateAgainstType($request->all(), $id);
            
            if (!empty($errors)) {
                return response()->json([
                    'valid' => false,
                    'errors' => $errors
                ], 422);
            }
            
            return response()->json([
                'valid' => true,
                'message' => 'Validation passed'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
