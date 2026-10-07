<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use AlamiaSoft\AlamiaAccounts\Models\CopilotKnowledgeEntry;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ManualController extends Controller
{
    /**
     * Get all structured manual chapters and ERP workflow playbooks.
     */
    public function index(Request $request): JsonResponse
    {
        $companyCode = $request->input('company_code') ?? $request->header('X-Company-Code');

        $chapters = $this->loadManualChapters();
        $sopWorkflows = $this->loadStandardGuidance();
        $customKnowledge = $this->loadCustomKnowledge($companyCode);

        return response()->json([
            'success' => true,
            'data' => [
                'system_name' => 'Alamia Accounts & ERP',
                'active_company' => $companyCode ?: 'MAIN',
                'chapters' => $chapters,
                'workflows' => $sopWorkflows,
                'custom_policies' => $customKnowledge,
            ],
        ]);
    }

    /**
     * Load architectural manual chapters from docs/manual.
     */
    protected function loadManualChapters(): array
    {
        $baseDirs = [
            base_path('../docs/manual'),
            base_path('docs/manual'),
            __DIR__ . '/../../../../docs/manual',
        ];

        $manualDir = null;
        foreach ($baseDirs as $dir) {
            if (is_dir($dir)) {
                $manualDir = realpath($dir);
                break;
            }
        }

        if (!$manualDir) {
            return [];
        }

        $files = glob($manualDir . '/*.md');
        sort($files);
        $chapters = [];

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $parsed = $this->parseMarkdownFrontmatter($content);

            $chapters[] = [
                'id' => $parsed['id'] ?? basename($file, '.md'),
                'title' => $parsed['title'] ?? basename($file, '.md'),
                'module' => $parsed['module'] ?? 'General',
                'tags' => $parsed['tags'] ?? [],
                'api_endpoints' => $parsed['api_endpoints'] ?? [],
                'invariants' => $parsed['invariants'] ?? [],
                'body' => $parsed['body'] ?? '',
                'filename' => basename($file),
            ];
        }

        return $chapters;
    }

    /**
     * Load standard ERP workflows from docs/copilot/standard-guidance.
     */
    protected function loadStandardGuidance(): array
    {
        $baseDirs = [
            base_path('../docs/copilot/standard-guidance'),
            base_path('docs/copilot/standard-guidance'),
            __DIR__ . '/../../../../docs/copilot/standard-guidance',
        ];

        $guidanceDir = null;
        foreach ($baseDirs as $dir) {
            if (is_dir($dir)) {
                $guidanceDir = realpath($dir);
                break;
            }
        }

        if (!$guidanceDir) {
            return [];
        }

        $files = glob($guidanceDir . '/*.md');
        sort($files);
        $workflows = [];

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $parsed = $this->parseMarkdownFrontmatter($content);

            $workflows[] = [
                'topic' => $parsed['topic'] ?? basename($file, '.md'),
                'domain' => $parsed['domain'] ?? 'general',
                'title' => $parsed['title'] ?? basename($file, '.md'),
                'trigger_keywords' => $parsed['trigger_keywords'] ?? [],
                'actions' => $parsed['actions'] ?? [],
                'body' => $parsed['body'] ?? '',
                'filename' => basename($file),
            ];
        }

        return $workflows;
    }

    /**
     * Load active custom knowledge rules from database.
     */
    protected function loadCustomKnowledge(?string $companyCode): array
    {
        if (!class_exists(CopilotKnowledgeEntry::class)) {
            return [];
        }

        return CopilotKnowledgeEntry::where('is_active', true)
            ->where(function ($q) use ($companyCode) {
                $q->whereNull('company_code');
                if ($companyCode) {
                    $q->orWhere('company_code', $companyCode);
                }
            })
            ->orderBy('id', 'asc')
            ->get()
            ->toArray();
    }

    /**
     * Basic YAML frontmatter parser for markdown documents.
     */
    protected function parseMarkdownFrontmatter(string $content): array
    {
        $result = ['body' => $content];

        if (preg_match('/^---\r?\n(.*?)\r?\n---\r?\n(.*)$/s', $content, $matches)) {
            $yaml = $matches[1];
            $body = trim($matches[2]);
            $result['body'] = $body;

            $lines = explode("\n", $yaml);
            $currentListKey = null;

            foreach ($lines as $line) {
                $line = rtrim($line);
                if (empty($line) || str_starts_with(trim($line), '#')) continue;

                if (preg_match('/^(\w+):\s*(.*)$/', $line, $m)) {
                    $key = trim($m[1]);
                    $val = trim($m[2]);
                    if ($val === '') {
                        $currentListKey = $key;
                        $result[$key] = [];
                    } else {
                        $currentListKey = null;
                        $result[$key] = trim($val, '"\'[]');
                    }
                } elseif ($currentListKey && preg_match('/^\s*-\s*(.*)$/', $line, $m)) {
                    $result[$currentListKey][] = trim($m[1], '"\'');
                }
            }
        }

        return $result;
    }
}
