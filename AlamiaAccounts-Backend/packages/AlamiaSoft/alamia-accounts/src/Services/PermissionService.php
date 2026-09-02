<?php

namespace AlamiaSoft\AlamiaAccounts\Services;

use Illuminate\Support\Facades\DB;
use Exception;

class PermissionService
{
    /**
     * Check if user can create voucher
     */
    public function canCreateVoucher(string $userId, string $voucherType): bool
    {
        return $this->hasPermission($userId, 'voucher', 'create', ['voucher_type' => $voucherType]);
    }
    
    /**
     * Check if user can approve voucher
     */
    public function canApproveVoucher(string $userId, string $voucherType, float $amount): bool
    {
        // Check basic permission
        if (!$this->hasPermission($userId, 'voucher', 'approve', ['voucher_type' => $voucherType])) {
            return false;
        }
        
        // Check amount threshold
        $threshold = $this->getApprovalThreshold($userId, $voucherType);
        
        if ($threshold && $amount > $threshold) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if user can view account
     */
    public function canViewAccount(string $userId, string $accountCode): bool
    {
        return $this->hasPermission($userId, 'account', 'view', ['account_code' => $accountCode]);
    }
    
    /**
     * Get field visibility for user
     */
    public function getFieldVisibility(string $userId, string $voucherType): array
    {
        $permissions = DB::table('field_level_permissions')
            ->join('user_roles', 'field_level_permissions.role_id', '=', 'user_roles.role_id')
            ->where('user_roles.user_id', $userId)
            ->where('field_level_permissions.voucher_type', $voucherType)
            ->get();
        
        $visibility = [];
        
        foreach ($permissions as $permission) {
            $visibility[$permission->field_name] = [
                'visible' => $permission->visible,
                'editable' => $permission->editable,
            ];
        }
        
        return $visibility;
    }
    
    /**
     * Grant permission to user/role
     */
    public function grantPermission(string $roleId, string $entity, string $action, array $constraints = []): bool
    {
        try {
            DB::table('accounting_permissions')->insert([
                'role_id' => $roleId,
                'entity_type' => $entity,
                'action' => $action,
                'constraints' => json_encode($constraints),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            return true;
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Revoke permission from user/role
     */
    public function revokePermission(string $roleId, string $entity, string $action): bool
    {
        try {
            DB::table('accounting_permissions')
                ->where('role_id', $roleId)
                ->where('entity_type', $entity)
                ->where('action', $action)
                ->delete();
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Set field-level permissions
     */
    public function setFieldPermissions(string $roleId, string $voucherType, array $fields): bool
    {
        DB::beginTransaction();
        
        try {
            // Delete existing permissions
            DB::table('field_level_permissions')
                ->where('role_id', $roleId)
                ->where('voucher_type', $voucherType)
                ->delete();
            
            // Insert new permissions
            foreach ($fields as $fieldName => $permissions) {
                DB::table('field_level_permissions')->insert([
                    'role_id' => $roleId,
                    'voucher_type' => $voucherType,
                    'field_name' => $fieldName,
                    'visible' => $permissions['visible'] ?? true,
                    'editable' => $permissions['editable'] ?? true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            
            DB::commit();
            return true;
            
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * Get all permissions for a role
     */
    public function getRolePermissions(string $roleId): array
    {
        return DB::table('accounting_permissions')
            ->where('role_id', $roleId)
            ->get()
            ->map(function($permission) {
                return [
                    'entity_type' => $permission->entity_type,
                    'action' => $permission->action,
                    'constraints' => json_decode($permission->constraints, true),
                ];
            })
            ->toArray();
    }
    
    /**
     * Check if user has specific permission
     */
    public function hasPermission(string $userId, string $entity, string $action, array $context = []): bool
    {
        // Get user's roles
        $roles = DB::table('user_roles')
            ->where('user_id', $userId)
            ->pluck('role_id');
        
        if ($roles->isEmpty()) {
            return false;
        }
        
        // Check permissions
        $permissions = DB::table('accounting_permissions')
            ->whereIn('role_id', $roles)
            ->where('entity_type', $entity)
            ->where('action', $action)
            ->get();
        
        if ($permissions->isEmpty()) {
            return false;
        }
        
        // Check constraints
        foreach ($permissions as $permission) {
            $constraints = json_decode($permission->constraints, true) ?? [];
            
            if (empty($constraints)) {
                return true; // No constraints, permission granted
            }
            
            // Check if context matches constraints
            $constraintsMet = true;
            foreach ($constraints as $key => $value) {
                if (!isset($context[$key]) || $context[$key] !== $value) {
                    $constraintsMet = false;
                    break;
                }
            }
            
            if ($constraintsMet) {
                return true;
            }
        }
        
        return false;
    }
    
    // Private helper methods
    
    private function getApprovalThreshold(string $userId, string $voucherType): ?float
    {
        $roles = DB::table('user_roles')
            ->where('user_id', $userId)
            ->pluck('role_id');
        
        $threshold = DB::table('accounting_permissions')
            ->whereIn('role_id', $roles)
            ->where('entity_type', 'voucher')
            ->where('action', 'approve')
            ->value('constraints');
        
        if ($threshold) {
            $constraints = json_decode($threshold, true);
            return $constraints['max_amount'] ?? null;
        }
        
        return null;
    }
}
