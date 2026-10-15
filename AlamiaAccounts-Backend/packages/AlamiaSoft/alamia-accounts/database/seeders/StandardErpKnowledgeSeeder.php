<?php

namespace AlamiaSoft\AlamiaAccounts\Database\Seeders;

use Illuminate\Database\Seeder;
use AlamiaSoft\AlamiaAccounts\Models\CopilotKnowledgeEntry;
use Symfony\Component\Yaml\Yaml;

class StandardErpKnowledgeSeeder extends Seeder
{
    /**
     * Seed standard ERP knowledge from version-controlled markdown files.
     */
    public function run(): void
    {
        $baseDirs = [
            base_path('../docs/copilot/standard-guidance'),
            base_path('docs/copilot/standard-guidance'),
            __DIR__ . '/../../docs/copilot/standard-guidance',
        ];

        $guidanceDir = null;
        foreach ($baseDirs as $dir) {
            if (is_dir($dir)) {
                $guidanceDir = realpath($dir);
                break;
            }
        }

        if (!$guidanceDir) {
            $this->command?->warn("Standard guidance directory not found. Skipping knowledge seed.");
            return;
        }

        $files = glob($guidanceDir . '/*.md');
        $seededCount = 0;

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $parsed = $this->parseMarkdownFile($content);

            if (empty($parsed['topic']) || empty($parsed['title'])) {
                continue;
            }

            CopilotKnowledgeEntry::updateOrCreate(
                [
                    'topic' => $parsed['topic'],
                    'company_code' => null, // Global universal rule
                ],
                [
                    'domain' => $parsed['domain'] ?? 'general',
                    'title' => $parsed['title'],
                    'trigger_keywords' => $parsed['trigger_keywords'] ?? [$parsed['topic']],
                    'summary' => $parsed['summary'] ?? '',
                    'steps' => $parsed['steps'] ?? [],
                    'note' => $parsed['note'] ?? null,
                    'actions' => $parsed['actions'] ?? [],
                    'is_active' => true,
                    'promoted_by' => 'system_seeder',
                    'promoted_at' => now(),
                    'change_reason' => 'Synced from standard guidance markdown file: ' . basename($file),
                    'version' => 1,
                ]
            );

            $seededCount++;
        }

        $this->command?->info("Successfully synced {$seededCount} standard ERP knowledge rules into database.");
    }

    /**
     * Parse markdown file with YAML frontmatter.
     */
    protected function parseMarkdownFile(string $content): array
    {
        $result = [
            'topic' => '',
            'domain' => 'general',
            'title' => '',
            'trigger_keywords' => [],
            'actions' => [],
            'summary' => '',
            'steps' => [],
            'note' => null,
        ];

        if (preg_match('/^---\r?\n(.*?)\r?\n---\r?\n(.*)$/s', $content, $matches)) {
            $yaml = $matches[1];
            $body = trim($matches[2]);

            // Parse YAML frontmatter using Symfony Yaml or basic fallback
            if (class_exists(Yaml::class)) {
                $frontmatter = Yaml::parse($yaml);
            } else {
                $frontmatter = $this->basicYamlParse($yaml);
            }

            if (is_array($frontmatter)) {
                $result = array_merge($result, $frontmatter);
            }

            // Extract note from body
            if (preg_match('/>\s*\[!NOTE\]\r?\n>\s*(.*)/i', $body, $noteMatch)) {
                $result['note'] = trim($noteMatch[1]);
                $body = preg_replace('/>\s*\[!NOTE\]\r?\n>\s*.*$/s', '', $body);
            }

            // Extract steps from body
            $steps = [];
            if (preg_match_all('/^\d+\.\s*(.*)$/m', $body, $stepMatches)) {
                foreach ($stepMatches[1] as $step) {
                    $steps[] = trim($step);
                }
            }
            $result['steps'] = $steps;

            // Summary is the text before ### Operational Steps: or first paragraph
            $parts = preg_split('/###\s+Operational Steps:/i', $body);
            $summaryText = trim($parts[0]);
            $result['summary'] = $summaryText;
        }

        return $result;
    }

    /**
     * Basic fallback YAML parser for environments without Symfony Yaml component.
     */
    protected function basicYamlParse(string $yaml): array
    {
        $data = [];
        $lines = explode("\n", $yaml);
        $currentListKey = null;

        foreach ($lines as $line) {
            $line = rtrim($line);
            if (empty($line) || str_starts_with(trim($line), '#')) {
                continue;
            }

            if (preg_match('/^(\w+):\s*(.*)$/', $line, $m)) {
                $key = trim($m[1]);
                $val = trim($m[2]);
                if ($val === '') {
                    $currentListKey = $key;
                    $data[$key] = [];
                } else {
                    $currentListKey = null;
                    $data[$key] = trim($val, '"\'');
                }
            } elseif ($currentListKey && preg_match('/^\s*-\s*(.*)$/', $line, $m)) {
                $data[$currentListKey][] = trim($m[1], '"\'');
            }
        }

        return $data;
    }
}
