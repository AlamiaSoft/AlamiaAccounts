<?php

namespace AlamiaSoft\AlamiaAccounts\Services;

use Illuminate\Support\Facades\DB;
use Exception;

class AutomationService
{
    /**
     * Apply post-save actions to a voucher
     */
    public function applyPostSaveActions(string $voucherId, array $actions): array
    {
        $results = [];
        
        foreach ($actions as $action) {
            try {
                switch ($action['type']) {
                    case 'auto_post_ledger':
                        $results[] = $this->autoPostToLedger($voucherId, $action['mapping']);
                        break;
                        
                    case 'update_field':
                        $results[] = $this->updateField($voucherId, $action['field'], $action['value']);
                        break;
                        
                    default:
                        $results[] = ['status' => 'skipped', 'action' => $action['type']];
                }
            } catch (Exception $e) {
                $results[] = ['status' => 'error', 'action' => $action['type'], 'error' => $e->getMessage()];
            }
        }
        
        return $results;
    }
    
    /**
     * Evaluate workflow rules
     */
    public function evaluateWorkflowRules(array $data, array $rules): array
    {
        $triggeredRules = [];
        
        foreach ($rules as $rule) {
            if ($this->evaluateCondition($data, $rule['condition'])) {
                $triggeredRules[] = [
                    'rule_id' => $rule['id'],
                    'action' => $rule['action'],
                    'params' => $rule['params'] ?? [],
                ];
            }
        }
        
        return $triggeredRules;
    }
    
    /**
     * Auto-post voucher to ledger
     */
    public function autoPostToLedger(string $voucherId, array $mapping): array
    {
        try {
            // Get voucher data
            $voucher = DB::table('journal_entries')->where('entry_id', $voucherId)->first();
            
            if (!$voucher) {
                throw new Exception("Voucher not found");
            }
            
            // Apply mapping rules to create ledger entries
            $entries = [];
            
            foreach ($mapping as $map) {
                $entries[] = [
                    'account_code' => $map['account_code'],
                    'amount' => $this->calculateAmount($voucher, $map['amount_formula']),
                    'type' => $map['type'], // 'debit' or 'credit'
                    'description' => $map['description'] ?? $voucher->description,
                ];
            }
            
            // Post entries (would integrate with VoucherService)
            // For now, just return the prepared entries
            
            return [
                'status' => 'success',
                'voucher_id' => $voucherId,
                'entries' => $entries,
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Create automation rule
     */
    public function createAutomationRule(array $data): int
    {
        return DB::table('automation_rules')->insertGetId([
            'name' => $data['name'],
            'trigger_event' => $data['trigger_event'],
            'conditions' => json_encode($data['conditions'] ?? []),
            'actions' => json_encode($data['actions']),
            'active' => $data['active'] ?? true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
    
    /**
     * Get automation rules for an event
     */
    public function getRulesForEvent(string $event): array
    {
        return DB::table('automation_rules')
            ->where('trigger_event', $event)
            ->where('active', true)
            ->get()
            ->map(function($rule) {
                return [
                    'id' => $rule->id,
                    'name' => $rule->name,
                    'conditions' => json_decode($rule->conditions, true),
                    'actions' => json_decode($rule->actions, true),
                ];
            })
            ->toArray();
    }
    
    /**
     * Execute automation rules
     */
    public function executeRules(string $event, array $context): array
    {
        $rules = $this->getRulesForEvent($event);
        $results = [];
        
        foreach ($rules as $rule) {
            // Check if conditions are met
            $conditionsMet = true;
            foreach ($rule['conditions'] as $condition) {
                if (!$this->evaluateCondition($context, $condition)) {
                    $conditionsMet = false;
                    break;
                }
            }
            
            // Execute actions if conditions are met
            if ($conditionsMet) {
                $results[] = [
                    'rule_id' => $rule['id'],
                    'rule_name' => $rule['name'],
                    'actions_executed' => $this->executeActions($rule['actions'], $context),
                ];
            }
        }
        
        return $results;
    }
    
    // Private helper methods
    
    private function evaluateCondition(array $data, array $condition): bool
    {
        $field = $condition['field'];
        $operator = $condition['operator'];
        $value = $condition['value'];
        
        $fieldValue = $data[$field] ?? null;
        
        switch ($operator) {
            case '=':
            case '==':
                return $fieldValue == $value;
                
            case '!=':
                return $fieldValue != $value;
                
            case '>':
                return $fieldValue > $value;
                
            case '<':
                return $fieldValue < $value;
                
            case '>=':
                return $fieldValue >= $value;
                
            case '<=':
                return $fieldValue <= $value;
                
            case 'contains':
                return strpos($fieldValue, $value) !== false;
                
            case 'in':
                return in_array($fieldValue, (array)$value);
                
            default:
                return false;
        }
    }
    
    private function executeActions(array $actions, array $context): array
    {
        $results = [];
        
        foreach ($actions as $action) {
            // Actions would be executed here
            // This is a placeholder - actual implementation would depend on action types
            $results[] = [
                'action_type' => $action['type'],
                'status' => 'executed',
            ];
        }
        
        return $results;
    }
    
    private function calculateAmount($voucher, string $formula): float
    {
        // Simple formula evaluation
        // In production, use a proper expression evaluator
        $amount = 0;
        
        // Replace variables with values
        $formula = str_replace('$amount', $voucher->amount ?? 0, $formula);
        
        try {
            $amount = eval("return $formula;");
        } catch (Exception $e) {
            $amount = 0;
        }
        
        return $amount;
    }
    
    private function updateField(string $voucherId, string $field, $value): array
    {
        try {
            DB::table('journal_entries')
                ->where('entry_id', $voucherId)
                ->update([$field => $value]);
            
            return ['status' => 'success', 'field' => $field];
        } catch (Exception $e) {
            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }
}
