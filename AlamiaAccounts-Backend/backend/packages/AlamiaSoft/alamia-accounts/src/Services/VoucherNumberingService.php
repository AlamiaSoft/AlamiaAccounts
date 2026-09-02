<?php

namespace AlamiaSoft\AlamiaAccounts\Services;

use Illuminate\Support\Facades\DB;
use Exception;

class VoucherNumberingService
{
    /**
     * Generate voucher number based on scheme
     */
    public function generateNumber(int $voucherTypeId, array $scheme): string
    {
        $prefix = $scheme['prefix'] ?? '';
        $separator = $this->getSeparator($scheme);
        $padding = $scheme['padding'] ?? 4;
        $includeYear = $scheme['include_year'] ?? false;
        $includeMonth = $scheme['include_month'] ?? false;
        
        $parts = [$prefix];
        
        // Add year if required
        if ($includeYear) {
            $parts[] = date('Y');
        }
        
        // Add month if required
        if ($includeMonth) {
            $parts[] = date('m');
        }
        
        // Get next number
        $nextNumber = $this->getNextNumber($voucherTypeId, $scheme);
        $parts[] = str_pad($nextNumber, $padding, '0', STR_PAD_LEFT);
        
        // Join with separator
        if ($separator === '') {
            return implode('', $parts);
        }
        
        return implode($separator, $parts);
    }
    
    /**
     * Get next number in sequence
     */
    public function getNextNumber(int $voucherTypeId, array $scheme): int
    {
        $resetPeriod = $scheme['reset_period'] ?? 'never';
        $startingNumber = $scheme['starting_number'] ?? 1;
        
        // Get current sequence
        $sequence = DB::table('voucher_number_sequences')
            ->where('voucher_type_id', $voucherTypeId)
            ->where('period', $this->getCurrentPeriod($resetPeriod))
            ->first();
        
        if (!$sequence) {
            // Create new sequence
            DB::table('voucher_number_sequences')->insert([
                'voucher_type_id' => $voucherTypeId,
                'period' => $this->getCurrentPeriod($resetPeriod),
                'current_number' => $startingNumber,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            return $startingNumber;
        }
        
        // Increment and return
        $nextNumber = $sequence->current_number + 1;
        
        DB::table('voucher_number_sequences')
            ->where('id', $sequence->id)
            ->update([
                'current_number' => $nextNumber,
                'updated_at' => now(),
            ]);
        
        return $nextNumber;
    }
    
    /**
     * Reset numbering for a voucher type
     */
    public function resetNumbering(int $voucherTypeId, string $period = 'never'): bool
    {
        try {
            DB::table('voucher_number_sequences')
                ->where('voucher_type_id', $voucherTypeId)
                ->where('period', $this->getCurrentPeriod($period))
                ->delete();
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Validate numbering scheme
     */
    public function validateNumberingScheme(array $scheme): bool
    {
        // Check required fields
        if (empty($scheme['prefix'])) {
            return false;
        }
        
        // Validate padding
        $padding = $scheme['padding'] ?? 4;
        if ($padding < 0 || $padding > 10) {
            return false;
        }
        
        // Validate reset period
        $resetPeriod = $scheme['reset_period'] ?? 'never';
        if (!in_array($resetPeriod, ['never', 'yearly', 'monthly', 'quarterly'])) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get numbering scheme for voucher type
     */
    public function getNumberingScheme(int $voucherTypeId): ?array
    {
        $scheme = DB::table('voucher_numbering_schemes')
            ->where('voucher_type_id', $voucherTypeId)
            ->first();
        
        if (!$scheme) {
            return null;
        }
        
        return [
            'id' => $scheme->id,
            'voucher_type_id' => $scheme->voucher_type_id,
            'prefix' => $scheme->prefix,
            'starting_number' => $scheme->starting_number,
            'padding' => $scheme->padding,
            'separator' => $scheme->separator,
            'custom_separator' => $scheme->custom_separator,
            'include_year' => $scheme->include_year,
            'include_month' => $scheme->include_month,
            'reset_period' => $scheme->reset_period,
        ];
    }
    
    /**
     * Save numbering scheme
     */
    public function saveNumberingScheme(int $voucherTypeId, array $scheme): bool
    {
        if (!$this->validateNumberingScheme($scheme)) {
            throw new Exception("Invalid numbering scheme");
        }
        
        try {
            DB::table('voucher_numbering_schemes')->updateOrInsert(
                ['voucher_type_id' => $voucherTypeId],
                [
                    'prefix' => $scheme['prefix'],
                    'starting_number' => $scheme['starting_number'] ?? 1,
                    'padding' => $scheme['padding'] ?? 4,
                    'separator' => $scheme['separator'] ?? '-',
                    'custom_separator' => $scheme['custom_separator'] ?? null,
                    'include_year' => $scheme['include_year'] ?? false,
                    'include_month' => $scheme['include_month'] ?? false,
                    'reset_period' => $scheme['reset_period'] ?? 'never',
                    'updated_at' => now(),
                ]
            );
            
            return true;
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    // Private helper methods
    
    private function getSeparator(array $scheme): string
    {
        $separator = $scheme['separator'] ?? '-';
        
        if ($separator === 'none') {
            return '';
        }
        
        if ($separator === 'custom') {
            return $scheme['custom_separator'] ?? '-';
        }
        
        return $separator;
    }
    
    private function getCurrentPeriod(string $resetPeriod): string
    {
        switch ($resetPeriod) {
            case 'yearly':
                return date('Y');
                
            case 'monthly':
                return date('Y-m');
                
            case 'quarterly':
                $month = date('n');
                $quarter = ceil($month / 3);
                return date('Y') . '-Q' . $quarter;
                
            case 'never':
            default:
                return 'default';
        }
    }
}
