<?php

namespace AlamiaSoft\AlamiaAccounts\Tests\Feature;

use Tests\TestCase;

class PublicCopilotRoutesTest extends TestCase
{
    public function test_public_capability_execution_routes_are_not_registered(): void
    {
        $this->postJson('/api/copilot/capabilities-public/post_voucher/execute', [
            'input' => ['reference' => 'TEST'],
        ])->assertNotFound();

        $this->postJson('/api/alamia-360/capabilities-public/post_voucher/execute', [
            'input' => ['reference' => 'TEST'],
        ])->assertNotFound();
    }

    public function test_public_diagnostics_and_knowledge_mutation_routes_are_not_registered(): void
    {
        $this->getJson('/api/copilot/diagnostics-public')->assertNotFound();
        $this->getJson('/api/copilot/diagnostics-public/export')->assertNotFound();
        $this->postJson('/api/copilot/knowledge-public', [
            'topic' => 'test',
            'trigger_keywords' => ['test topic'],
            'title' => 'Test',
            'summary' => 'Test',
        ])->assertNotFound();
        $this->deleteJson('/api/copilot/knowledge-public/1')->assertNotFound();
    }
}
