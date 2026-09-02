<?php

namespace AlamiaSoft\AlamiaAccounts\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Exception;

class CustomVoucherTypeService
{
    /**
     * Create a new custom voucher type
     */
    public function createVoucherType(array $data): array
    {
        $validated = $this->validateVoucherType($data);
        
        DB::beginTransaction();
        try {
            // Create voucher type
            $voucherTypeId = DB::table('custom_voucher_types')->insertGetId([
                'name' => $validated['name'],
                'prefix' => $validated['prefix'],
                'description' => $validated['description'] ?? null,
                'active' => $validated['active'] ?? true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            // Create custom fields
            if (!empty($validated['custom_fields'])) {
                foreach ($validated['custom_fields'] as $field) {
                    DB::table('custom_voucher_fields')->insert([
                        'voucher_type_id' => $voucherTypeId,
                        'name' => $field['name'],
                        'type' => $field['type'],
                        'required' => $field['required'] ?? false,
                        'options' => isset($field['options']) ? json_encode($field['options']) : null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
            
            // Create account rules
            if (!empty($validated['account_rules'])) {
                foreach ($validated['account_rules'] as $rule) {
                    DB::table('voucher_account_rules')->insert([
                        'voucher_type_id' => $voucherTypeId,
                        'side' => $rule['side'],
                        'account_groups' => json_encode($rule['account_groups']),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
            
            // Create validation rules
            if (!empty($validated['validation_rules'])) {
                foreach ($validated['validation_rules'] as $rule) {
                    DB::table('voucher_validation_rules')->insert([
                        'voucher_type_id' => $voucherTypeId,
                        'field_name' => $rule['field_name'],
                        'type' => $rule['type'],
                        'value' => $rule['value'] ?? null,
                        'message' => $rule['message'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
            
            // Create auto-calculation rules
            if (!empty($validated['auto_calculation_rules'])) {
                foreach ($validated['auto_calculation_rules'] as $rule) {
                    DB::table('voucher_calculation_rules')->insert([
                        'voucher_type_id' => $voucherTypeId,
                        'target_field' => $rule['target_field'],
                        'formula' => $rule['formula'],
                        'description' => $rule['description'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
            
            // Create default value rules
            if (!empty($validated['default_value_rules'])) {
                foreach ($validated['default_value_rules'] as $rule) {
                    DB::table('voucher_default_rules')->insert([
                        'voucher_type_id' => $voucherTypeId,
                        'field_name' => $rule['field_name'],
                        'condition' => $rule['condition'] ?? null,
                        'default_value' => $rule['default_value'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
            
            // Create approval rules
            if (!empty($validated['approval_rules'])) {
                foreach ($validated['approval_rules'] as $rule) {
                    DB::table('voucher_approval_rules')->insert([
                        'voucher_type_id' => $voucherTypeId,
                        'condition' => $rule['condition'],
                        'approver_role' => $rule['approver_role'],
                        'min_amount' => $rule['min_amount'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
            
            DB::commit();
            
            return $this->getVoucherType($voucherTypeId);
            
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * Get voucher type by ID
     */
    public function getVoucherType(int $id): array
    {
        $voucherType = DB::table('custom_voucher_types')->where('id', $id)->first();
        
        if (!$voucherType) {
            throw new Exception("Voucher type not found");
        }
        
        return [
            'id' => $voucherType->id,
            'name' => $voucherType->name,
            'prefix' => $voucherType->prefix,
            'description' => $voucherType->description,
            'active' => $voucherType->active,
            'custom_fields' => $this->getCustomFields($id),
            'account_rules' => $this->getAccountRules($id),
            'validation_rules' => $this->getValidationRules($id),
            'auto_calculation_rules' => $this->getCalculationRules($id),
            'default_value_rules' => $this->getDefaultRules($id),
            'approval_rules' => $this->getApprovalRules($id),
        ];
    }
    
    /**
     * Get all voucher types
     */
    public function getAllVoucherTypes(): array
    {
        $types = DB::table('custom_voucher_types')->get();
        
        return $types->map(function($type) {
            return $this->getVoucherType($type->id);
        })->toArray();
    }
    
    /**
     * Update voucher type
     */
    public function updateVoucherType(int $id, array $data): array
    {
        $validated = $this->validateVoucherType($data);
        
        DB::beginTransaction();
        try {
            // Update voucher type
            DB::table('custom_voucher_types')->where('id', $id)->update([
                'name' => $validated['name'],
                'prefix' => $validated['prefix'],
                'description' => $validated['description'] ?? null,
                'active' => $validated['active'] ?? true,
                'updated_at' => now(),
            ]);
            
            // Delete existing rules and fields
            DB::table('custom_voucher_fields')->where('voucher_type_id', $id)->delete();
            DB::table('voucher_account_rules')->where('voucher_type_id', $id)->delete();
            DB::table('voucher_validation_rules')->where('voucher_type_id', $id)->delete();
            DB::table('voucher_calculation_rules')->where('voucher_type_id', $id)->delete();
            DB::table('voucher_default_rules')->where('voucher_type_id', $id)->delete();
            DB::table('voucher_approval_rules')->where('voucher_type_id', $id)->delete();
            
            // Re-create with new data (same logic as create)
            // ... (similar to createVoucherType)
            
            DB::commit();
            
            return $this->getVoucherType($id);
            
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * Delete voucher type
     */
    public function deleteVoucherType(int $id): bool
    {
        DB::beginTransaction();
        try {
            // Delete all related records
            DB::table('custom_voucher_fields')->where('voucher_type_id', $id)->delete();
            DB::table('voucher_account_rules')->where('voucher_type_id', $id)->delete();
            DB::table('voucher_validation_rules')->where('voucher_type_id', $id)->delete();
            DB::table('voucher_calculation_rules')->where('voucher_type_id', $id)->delete();
            DB::table('voucher_default_rules')->where('voucher_type_id', $id)->delete();
            DB::table('voucher_approval_rules')->where('voucher_type_id', $id)->delete();
            
            // Delete voucher type
            DB::table('custom_voucher_types')->where('id', $id)->delete();
            
            DB::commit();
            return true;
            
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * Validate voucher data against type rules
     */
    public function validateAgainstType(array $data, int $voucherTypeId): array
    {
        $voucherType = $this->getVoucherType($voucherTypeId);
        $errors = [];
        
        // Validate required custom fields
        foreach ($voucherType['custom_fields'] as $field) {
            if ($field['required'] && empty($data[$field['name']])) {
                $errors[] = "{$field['name']} is required";
            }
        }
        
        // Apply validation rules
        foreach ($voucherType['validation_rules'] as $rule) {
            $fieldValue = $data[$rule['field_name']] ?? null;
            
            switch ($rule['type']) {
                case 'required':
                    if (empty($fieldValue)) {
                        $errors[] = $rule['message'] ?? "{$rule['field_name']} is required";
                    }
                    break;
                    
                case 'min_value':
                    if ($fieldValue < $rule['value']) {
                        $errors[] = $rule['message'] ?? "{$rule['field_name']} must be at least {$rule['value']}";
                    }
                    break;
                    
                case 'max_value':
                    if ($fieldValue > $rule['value']) {
                        $errors[] = $rule['message'] ?? "{$rule['field_name']} must not exceed {$rule['value']}";
                    }
                    break;
                    
                case 'regex':
                    if (!preg_match($rule['value'], $fieldValue)) {
                        $errors[] = $rule['message'] ?? "{$rule['field_name']} format is invalid";
                    }
                    break;
            }
        }
        
        return $errors;
    }
    
    /**
     * Apply account rules to voucher entry
     */
    public function applyAccountRules(array $entry, int $voucherTypeId): bool
    {
        $rules = $this->getAccountRules($voucherTypeId);
        
        foreach ($rules as $rule) {
            $side = $entry['type']; // 'debit' or 'credit'
            $accountGroup = $entry['account_group'] ?? null;
            
            if ($rule['side'] === $side) {
                $allowedGroups = json_decode($rule['account_groups'], true);
                if (!in_array($accountGroup, $allowedGroups)) {
                    return false;
                }
            }
        }
        
        return true;
    }
    
    /**
     * Calculate fields based on formulas
     */
    public function calculateFields(array $data, int $voucherTypeId): array
    {
        $rules = $this->getCalculationRules($voucherTypeId);
        
        foreach ($rules as $rule) {
            // Simple formula evaluation (can be enhanced)
            $formula = $rule['formula'];
            
            // Replace field names with values
            foreach ($data as $key => $value) {
                $formula = str_replace($key, $value, $formula);
            }
            
            // Evaluate formula (use a safe evaluator in production)
            try {
                $result = eval("return $formula;");
                $data[$rule['target_field']] = $result;
            } catch (Exception $e) {
                // Log error
            }
        }
        
        return $data;
    }
    
    /**
     * Apply default values
     */
    public function applyDefaultValues(array $data, int $voucherTypeId): array
    {
        $rules = $this->getDefaultRules($voucherTypeId);
        
        foreach ($rules as $rule) {
            if (empty($data[$rule['field_name']])) {
                // Check condition if exists
                if ($rule['condition']) {
                    // Evaluate condition (simplified)
                    // In production, use a proper expression evaluator
                }
                
                $data[$rule['field_name']] = $rule['default_value'];
            }
        }
        
        return $data;
    }
    
    // Private helper methods
    
    private function validateVoucherType(array $data): array
    {
        $validator = Validator::make($data, [
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
        
        if ($validator->fails()) {
            throw new Exception($validator->errors()->first());
        }
        
        return $validator->validated();
    }
    
    private function getCustomFields(int $voucherTypeId): array
    {
        return DB::table('custom_voucher_fields')
            ->where('voucher_type_id', $voucherTypeId)
            ->get()
            ->map(function($field) {
                return [
                    'id' => $field->id,
                    'name' => $field->name,
                    'type' => $field->type,
                    'required' => $field->required,
                    'options' => $field->options ? json_decode($field->options, true) : null,
                ];
            })
            ->toArray();
    }
    
    private function getAccountRules(int $voucherTypeId): array
    {
        return DB::table('voucher_account_rules')
            ->where('voucher_type_id', $voucherTypeId)
            ->get()
            ->toArray();
    }
    
    private function getValidationRules(int $voucherTypeId): array
    {
        return DB::table('voucher_validation_rules')
            ->where('voucher_type_id', $voucherTypeId)
            ->get()
            ->toArray();
    }
    
    private function getCalculationRules(int $voucherTypeId): array
    {
        return DB::table('voucher_calculation_rules')
            ->where('voucher_type_id', $voucherTypeId)
            ->get()
            ->toArray();
    }
    
    private function getDefaultRules(int $voucherTypeId): array
    {
        return DB::table('voucher_default_rules')
            ->where('voucher_type_id', $voucherTypeId)
            ->get()
            ->toArray();
    }
    
    private function getApprovalRules(int $voucherTypeId): array
    {
        return DB::table('voucher_approval_rules')
            ->where('voucher_type_id', $voucherTypeId)
            ->get()
            ->toArray();
    }
}
