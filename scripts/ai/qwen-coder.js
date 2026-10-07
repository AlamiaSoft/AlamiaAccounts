#!/usr/bin/env node
/**
 * Reusable Code Generation Utility powered by local Ollama (qwen2.5-coder:7b).
 * Can be used as a CLI tool or imported as a module by agents and scripts across projects.
 *
 * Usage CLI:
 *   node scripts/ai/qwen-coder.js --prompt "Write a Laravel migration for invoices"
 *   node scripts/ai/qwen-coder.js --system "You are a PHP expert" --prompt "Generate a helper class"
 *   node scripts/ai/qwen-coder.js --file prompt.txt --output Generated.php
 *   node scripts/ai/qwen-coder.js --prompt "Generate JSON schema for voucher" --json
 *
 * Usage Module:
 *   const { generateCode } = require('./scripts/ai/qwen-coder');
 *   const code = await generateCode({ prompt: '...', system: '...', model: 'qwen2.5-coder:7b' });
 */

const fs = require('fs');
const path = require('path');

function normalizeOllamaHost(host) {
  if (!host || host === '0.0.0.0') {
    return 'http://127.0.0.1:11434';
  }
  let normalized = host.trim();
  if (!normalized.startsWith('http://') && !normalized.startsWith('https://')) {
    normalized = 'http://' + normalized;
  }
  try {
    const parsed = new URL(normalized);
    if (!parsed.port && parsed.hostname === '0.0.0.0') {
      return 'http://127.0.0.1:11434';
    }
    if (!parsed.port) {
      parsed.port = '11434';
    }
    if (parsed.hostname === '0.0.0.0') {
      parsed.hostname = '127.0.0.1';
    }
    return parsed.origin;
  } catch {
    return 'http://127.0.0.1:11434';
  }
}

const OLLAMA_HOST = normalizeOllamaHost(process.env.OLLAMA_HOST);
const DEFAULT_MODEL = process.env.OLLAMA_CODER_MODEL || 'qwen2.5-coder:7b';

/**
 * Generate code using local Ollama model.
 * @param {Object} options
 * @param {string} options.prompt - Prompt instructions
 * @param {string} [options.system] - Optional system prompt
 * @param {string} [options.model] - Model name (default: qwen2.5-coder:7b)
 * @param {boolean} [options.json] - Whether to enforce JSON mode
 * @param {number} [options.temperature] - Sampling temperature (default: 0.2 for precise code)
 * @returns {Promise<string>}
 */
async function generateCode({
  prompt,
  system = 'You are an elite expert software engineer. Write clean, idiomatic, production-ready, well-tested code without unnecessary conversational filler. Return only the requested code or structured output.',
  model = DEFAULT_MODEL,
  json = false,
  temperature = 0.2,
}) {
  if (!prompt || typeof prompt !== 'string') {
    throw new Error('Missing required "prompt" parameter.');
  }

  const endpoint = `${OLLAMA_HOST}/api/generate`;
  const body = {
    model,
    prompt,
    system,
    stream: false,
    options: {
      temperature,
    },
  };

  if (json) {
    body.format = 'json';
  }

  try {
    const response = await fetch(endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });

    if (!response.ok) {
      const errText = await response.text();
      throw new Error(`Ollama API error (${response.status}): ${errText}`);
    }

    const data = await response.json();
    return data.response ? data.response.trim() : '';
  } catch (err) {
    if (err.code === 'ECONNREFUSED' || err.message.includes('fetch failed')) {
      throw new Error(
        `Failed to connect to Ollama at ${OLLAMA_HOST}. Ensure Ollama is running ('ollama serve' or desktop app).`
      );
    }
    throw err;
  }
}

// CLI Execution Handler
if (require.main === module) {
  (async () => {
    const args = process.argv.slice(2);
    let prompt = '';
    let system = undefined;
    let model = DEFAULT_MODEL;
    let jsonMode = false;
    let outputFile = null;
    let inputFile = null;

    for (let i = 0; i < args.length; i++) {
      const arg = args[i];
      if (arg === '--prompt' || arg === '-p') {
        prompt = args[++i];
      } else if (arg === '--system' || arg === '-s') {
        system = args[++i];
      } else if (arg === '--model' || arg === '-m') {
        model = args[++i];
      } else if (arg === '--file' || arg === '-f') {
        inputFile = args[++i];
      } else if (arg === '--output' || arg === '-o') {
        outputFile = args[++i];
      } else if (arg === '--json') {
        jsonMode = true;
      } else if (arg === '--help' || arg === '-h') {
        console.log(`
Qwen2.5-Coder Generation Utility
Usage:
  node qwen-coder.js --prompt "<prompt>" [options]
  node qwen-coder.js --file <input.txt> --output <output.php>

Options:
  -p, --prompt <string>    Prompt for code generation
  -f, --file <path>        Read prompt from file
  -s, --system <string>    System prompt
  -m, --model <name>       Ollama model (default: qwen2.5-coder:7b)
  -o, --output <path>      Save output to file instead of stdout
  --json                   Force JSON format output
  -h, --help               Show this help message
        `);
        process.exit(0);
      }
    }

    if (inputFile) {
      if (!fs.existsSync(inputFile)) {
        console.error(`Error: Input file "${inputFile}" does not exist.`);
        process.exit(1);
      }
      prompt = fs.readFileSync(inputFile, 'utf8');
    }

    if (!prompt) {
      console.error('Error: No prompt provided. Use --prompt "..." or --file <path>. Use --help for usage.');
      process.exit(1);
    }

    try {
      const startTime = Date.now();
      process.stderr.write(`[qwen-coder] Generating code using model: ${model}...\n`);
      const result = await generateCode({ prompt, system, model, json: jsonMode });
      const durationMs = Date.now() - startTime;
      process.stderr.write(`[qwen-coder] Generated in ${(durationMs / 1000).toFixed(2)}s\n`);

      if (outputFile) {
        const fullOutputPath = path.resolve(process.cwd(), outputFile);
        fs.mkdirSync(path.dirname(fullOutputPath), { recursive: true });
        fs.writeFileSync(fullOutputPath, result, 'utf8');
        console.log(`Code successfully written to ${outputFile}`);
      } else {
        console.log(result);
      }
    } catch (err) {
      console.error(`[qwen-coder] Error: ${err.message}`);
      process.exit(1);
    }
  })();
}

module.exports = { generateCode, DEFAULT_MODEL, OLLAMA_HOST };
