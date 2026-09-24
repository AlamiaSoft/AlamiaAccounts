<?php

namespace Alamia360\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'alamia360:install';

    protected $description = 'Publish Alamia 360 config, migrations and stubs';

    public function handle(): int
    {
        $this->info('Installing Alamia 360...');
        $this->newLine();

        // Publish config
        $this->callSilently('vendor:publish', [
            '--tag' => 'alamia360-config',
            '--force' => false,
        ]);
        $this->line('  <fg=green;options=bold>✓</> Config published → <comment>config/alamia360.php</comment>');

        // Publish migrations
        $this->callSilently('vendor:publish', [
            '--tag' => 'alamia360-migrations',
            '--force' => false,
        ]);
        $this->line('  <fg=green;options=bold>✓</> Migrations published → <comment>database/migrations/</comment>');

        $this->newLine();
        $this->info('Next steps:');
        $this->line('  1. Review <comment>config/alamia360.php</comment> and set environment variables');
        $this->line('  2. Run <comment>php artisan migrate</comment> to create Alamia 360 tables');
        $this->line('  3. Register your entities, capabilities and responsibilities in a ServiceProvider');
        $this->line('  4. Add <comment>SituationDetector</comment> implementations and register with <comment>Alamia360::observer()->addDetector(...)</comment>');
        $this->newLine();
        $this->line('  To publish stubs (optional):');
        $this->line('  <comment>php artisan vendor:publish --tag=alamia360-stubs</comment>');
        $this->newLine();
        $this->line('  Documentation: <comment>vendor/alamia/alamia-360/docs/</comment>');
        $this->newLine();

        return self::SUCCESS;
    }
}
