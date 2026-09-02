<?php

namespace AlamiaSoft\AlamiaAccounts\Services;

use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;

class PrintService
{
    /**
     * Generate voucher PDF
     */
    public function generateVoucherPDF(string $voucherId, array $template): string
    {
        // Get voucher data
        $voucher = DB::table('journal_entries')->where('entry_id', $voucherId)->first();
        
        if (!$voucher) {
            throw new Exception("Voucher not found");
        }
        
        // Get voucher details
        $details = DB::table('journal_details')
            ->join('ledger_accounts', 'journal_details.account_uuid', '=', 'ledger_accounts.account_uuid')
            ->where('journal_details.entry_id', $voucherId)
            ->select('journal_details.*', 'ledger_accounts.name as account_name', 'ledger_accounts.code as account_code')
            ->get();
        
        // Prepare data for template
        $data = [
            'voucher' => $voucher,
            'details' => $details,
            'template' => $template,
        ];
        
        // Generate PDF
        $pdf = Pdf::loadView('alamia-accounts::print.voucher', $data);
        
        // Save to temp file
        $filename = 'voucher_' . $voucher->reference . '_' . time() . '.pdf';
        $path = storage_path('app/temp/' . $filename);
        
        $pdf->save($path);
        
        return $path;
    }
    
    /**
     * Generate report PDF
     */
    public function generateReportPDF(string $reportType, array $data, array $template): string
    {
        // Prepare data for template
        $templateData = [
            'reportType' => $reportType,
            'data' => $data,
            'template' => $template,
            'generatedAt' => now(),
        ];
        
        // Generate PDF
        $pdf = Pdf::loadView('alamia-accounts::print.report', $templateData);
        
        // Save to temp file
        $filename = $reportType . '_' . time() . '.pdf';
        $path = storage_path('app/temp/' . $filename);
        
        $pdf->save($path);
        
        return $path;
    }
    
    /**
     * Apply template to data
     */
    public function applyTemplate(array $data, array $template): string
    {
        $html = $template['header'] ?? '';
        
        // Apply header
        if ($template['show_header'] ?? true) {
            $html .= $this->renderHeader($template);
        }
        
        // Apply content
        $html .= $this->renderContent($data, $template);
        
        // Apply footer
        if ($template['show_footer'] ?? true) {
            $html .= $this->renderFooter($template);
        }
        
        return $html;
    }
    
    /**
     * Get print template
     */
    public function getTemplate(string $companyCode, string $templateType): ?array
    {
        $template = DB::table('print_templates')
            ->where('company_code', $companyCode)
            ->where('template_type', $templateType)
            ->first();
        
        if (!$template) {
            // Return default template
            return $this->getDefaultTemplate($templateType);
        }
        
        return [
            'id' => $template->id,
            'company_code' => $template->company_code,
            'template_type' => $template->template_type,
            'company_name' => $template->company_name,
            'company_address' => $template->company_address,
            'company_phone' => $template->company_phone,
            'company_email' => $template->company_email,
            'logo_url' => $template->logo_url,
            'footer_note' => $template->footer_note,
            'show_header' => $template->show_header,
            'show_footer' => $template->show_footer,
            'custom_css' => $template->custom_css,
        ];
    }
    
    /**
     * Save print template
     */
    public function saveTemplate(string $companyCode, string $templateType, array $data): bool
    {
        try {
            DB::table('print_templates')->updateOrInsert(
                [
                    'company_code' => $companyCode,
                    'template_type' => $templateType,
                ],
                [
                    'company_name' => $data['company_name'] ?? null,
                    'company_address' => $data['company_address'] ?? null,
                    'company_phone' => $data['company_phone'] ?? null,
                    'company_email' => $data['company_email'] ?? null,
                    'logo_url' => $data['logo_url'] ?? null,
                    'footer_note' => $data['footer_note'] ?? null,
                    'show_header' => $data['show_header'] ?? true,
                    'show_footer' => $data['show_footer'] ?? true,
                    'custom_css' => $data['custom_css'] ?? null,
                    'updated_at' => now(),
                ]
            );
            
            return true;
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    // Private helper methods
    
    private function renderHeader(array $template): string
    {
        $html = '<div class="header">';
        
        if (!empty($template['logo_url'])) {
            $html .= '<img src="' . $template['logo_url'] . '" alt="Logo" class="logo">';
        }
        
        $html .= '<h1>' . ($template['company_name'] ?? '') . '</h1>';
        
        if (!empty($template['company_address'])) {
            $html .= '<p>' . $template['company_address'] . '</p>';
        }
        
        if (!empty($template['company_phone']) || !empty($template['company_email'])) {
            $html .= '<p>';
            if (!empty($template['company_phone'])) {
                $html .= 'Tel: ' . $template['company_phone'] . ' ';
            }
            if (!empty($template['company_email'])) {
                $html .= 'Email: ' . $template['company_email'];
            }
            $html .= '</p>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    private function renderContent(array $data, array $template): string
    {
        // This would be customized based on template type
        return '<div class="content">' . json_encode($data) . '</div>';
    }
    
    private function renderFooter(array $template): string
    {
        $html = '<div class="footer">';
        
        if (!empty($template['footer_note'])) {
            $html .= '<p>' . $template['footer_note'] . '</p>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    private function getDefaultTemplate(string $templateType): array
    {
        return [
            'template_type' => $templateType,
            'company_name' => 'Company Name',
            'show_header' => true,
            'show_footer' => true,
            'footer_note' => 'This is a computer generated document.',
        ];
    }
}
