<?php

namespace AlamiaSoft\AlamiaAccounts\Http\Controllers\Api;

use AlamiaSoft\AlamiaAccounts\Http\Controllers\Controller;
use Illuminate\Http\Request;
use AlamiaSoft\AlamiaAccounts\Services\PrintService;
use AlamiaSoft\AlamiaAccounts\Services\DomainContext;

/**
 * @OA\Tag(name="Print", description="Operations related to Document Printing and Templates")
 */
class PrintController extends Controller
{
    protected $printService;

    public function __construct(PrintService $printService)
    {
        $this->printService = $printService;
    }

    /**
     * @OA\Get(
     *     path="/print/voucher/{voucherId}",
     *     summary="Generate PDF for a voucher",
     *     tags={"Print"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="voucherId", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="PDF file stream")
     * )
     */

    /**
     * Print a voucher
     */
    public function printVoucher(Request $request, $voucherId)
    {
        try {
            $companyCode = DomainContext::get();
            $template = $this->printService->getTemplate($companyCode, 'voucher');
            
            $pdfPath = $this->printService->generateVoucherPDF($voucherId, $template);
            
            return response()->download($pdfPath)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/print/report",
     *     summary="Generate PDF for a report",
     *     tags={"Print"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"report_type", "data"},
     *             @OA\Property(property="report_type", type="string", enum={"trial_balance", "profit_loss", "balance_sheet", "cash_flow"}),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=200, description="PDF file stream")
     * )
     */

    /**
     * Print a report
     */
    public function printReport(Request $request)
    {
        $validated = $request->validate([
            'report_type' => 'required|string|in:trial_balance,profit_loss,balance_sheet,cash_flow',
            'data' => 'required|array',
        ]);

        try {
            $companyCode = DomainContext::get();
            $template = $this->printService->getTemplate($companyCode, 'report');
            
            $pdfPath = $this->printService->generateReportPDF(
                $validated['report_type'],
                $validated['data'],
                $template
            );
            
            return response()->download($pdfPath)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/print/template",
     *     summary="Get print template",
     *     tags={"Print"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="template_type", in="query", required=true, @OA\Schema(type="string", enum={"voucher", "report"})),
     *     @OA\Parameter(name="company_code", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */

    /**
     * Get print template
     */
    public function getTemplate(Request $request)
    {
        $validated = $request->validate([
            'template_type' => 'required|string|in:voucher,report',
            'company_code' => 'nullable|string',
        ]);

        $companyCode = $validated['company_code'] ?? DomainContext::get();

        try {
            $template = $this->printService->getTemplate($companyCode, $validated['template_type']);
            
            return response()->json([
                'data' => $template
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/print/template",
     *     summary="Save print template",
     *     tags={"Print"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"template_type"},
     *             @OA\Property(property="template_type", type="string", enum={"voucher", "report"}),
     *             @OA\Property(property="company_name", type="string", example="My Company")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Template saved successfully")
     * )
     */

    /**
     * Save print template
     */
    public function saveTemplate(Request $request)
    {
        $validated = $request->validate([
            'template_type' => 'required|string|in:voucher,report',
            'company_code' => 'nullable|string',
            'company_name' => 'nullable|string',
            'company_address' => 'nullable|string',
            'company_phone' => 'nullable|string',
            'company_email' => 'nullable|email',
            'logo_url' => 'nullable|url',
            'footer_note' => 'nullable|string',
            'show_header' => 'boolean',
            'show_footer' => 'boolean',
            'custom_css' => 'nullable|string',
        ]);

        $companyCode = $validated['company_code'] ?? DomainContext::get();
        $templateType = $validated['template_type'];
        unset($validated['template_type'], $validated['company_code']);

        try {
            $this->printService->saveTemplate($companyCode, $templateType, $validated);
            
            return response()->json([
                'message' => 'Template saved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload logo (app-specific file handling)
     */
    public function uploadLogo(Request $request)
    {
        $request->validate([
            'logo' => 'required|image|max:2048', // 2MB max
            'company_code' => 'nullable|string',
        ]);

        try {
            $companyCode = $request->input('company_code', DomainContext::get());
            
            // Store logo
            $path = $request->file('logo')->store('logos', 'public');
            $url = asset('storage/' . $path);
            
            return response()->json([
                'url' => $url,
                'path' => $path,
                'message' => 'Logo uploaded successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
