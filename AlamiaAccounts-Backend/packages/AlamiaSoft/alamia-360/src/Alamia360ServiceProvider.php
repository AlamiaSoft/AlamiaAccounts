<?php

namespace Alamia360;

use Alamia360\Actors\ActorRegistry;
use Alamia360\AI\AiHarness;
use Alamia360\AI\NullAiProvider;
use Alamia360\Capabilities\CapabilityExecutor;
use Alamia360\Capabilities\CapabilityRegistry;
use Alamia360\Console\InstallCommand;
use Alamia360\Context\ContextBuilder;
use Alamia360\Contracts\AiProviderContract;
use Alamia360\Contracts\ResponsibilityResolverContract;
use Alamia360\Infrastructure\AuditService;
use Alamia360\Infrastructure\Repositories\EloquentAuditRepository;
use Alamia360\Infrastructure\Repositories\EloquentObservationRepository;
use Alamia360\Infrastructure\Repositories\EloquentSituationRepository;
use Alamia360\MCP\McpAdapter;
use Alamia360\Observation\Observer;
use Alamia360\Orchestration\Orchestrator;
use Alamia360\Responsibilities\DefaultResponsibilityResolver;
use Alamia360\Responsibilities\ResponsibilityRegistry;
use Alamia360\Semantic\SemanticRegistry;
use Alamia360\Situations\SituationRegistry;
use Illuminate\Support\ServiceProvider;

class Alamia360ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/alamia360.php', 'alamia360');

        // Core registries — singletons so definitions accumulate app-wide.
        $this->app->singleton(SemanticRegistry::class);
        $this->app->singleton(CapabilityRegistry::class);
        $this->app->singleton(ResponsibilityRegistry::class);
        $this->app->singleton(ActorRegistry::class);
        $this->app->singleton(SituationRegistry::class);
        $this->app->singleton(Observer::class);

        $this->app->singleton(ResponsibilityResolverContract::class, DefaultResponsibilityResolver::class);
        $this->app->singleton(CapabilityExecutor::class);
        $this->app->singleton(Orchestrator::class);
        $this->app->singleton(ContextBuilder::class);

        // AI is opt-in: bind NullAiProvider unless the host app rebinds
        // AiProviderContract to a real provider.
        $this->app->bind(AiProviderContract::class, NullAiProvider::class);
        $this->app->singleton(AiHarness::class);

        // Infrastructure — repositories and audit service.
        $this->app->singleton(EloquentSituationRepository::class);
        $this->app->singleton(EloquentObservationRepository::class);
        $this->app->singleton(EloquentAuditRepository::class);
        $this->app->singleton(AuditService::class);

        $this->app->singleton(McpAdapter::class);

        $this->app->singleton('alamia360', fn ($app) => new Alamia360Manager(
            $app->make(SemanticRegistry::class),
            $app->make(CapabilityRegistry::class),
            $app->make(ResponsibilityRegistry::class),
            $app->make(ActorRegistry::class),
            $app->make(Observer::class),
            $app->make(SituationRegistry::class),
            $app->make(Orchestrator::class),
            $app->make(ContextBuilder::class),
            $app->make(CapabilityExecutor::class),
        ));
    }

    public function boot(): void
    {
        // Always load migrations — host app can still publish them if desired.
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__ . '/../config/alamia360.php' => config_path('alamia360.php'),
        ], 'alamia360-config');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'alamia360-migrations');

        $this->publishes([
            __DIR__ . '/../stubs' => base_path('stubs/alamia360'),
        ], 'alamia360-stubs');

        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class]);
        }

        $this->wirePersistence();
        $this->wireAudit();
    }

    /**
     * Wire Eloquent persistence bridges when the driver is 'eloquent'.
     * Uses closures so repositories are only resolved if actually needed.
     */
    protected function wirePersistence(): void
    {
        if (config('alamia360.persistence.driver') !== 'eloquent') {
            return;
        }

        // Situation persistence
        $this->app->make(SituationRegistry::class)->persistUsing(
            fn ($situation) => $this->app->make(EloquentSituationRepository::class)->save($situation)
        );

        // Observation persistence (sync, always — the audit trail requires it)
        if (config('alamia360.observation.persist', true)) {
            $this->app->make(Observer::class)->persistUsing(
                fn ($observation) => $this->app->make(EloquentObservationRepository::class)->save($observation)
            );
        }

        // Async detection via queue
        if (config('alamia360.observation.queue', false)) {
            $connection = config('alamia360.observation.queue_connection');
            $this->app->make(Observer::class)->queueDetection(true, $connection);
        }
    }

    /**
     * Wire the AuditService into CapabilityExecutor's audit hook.
     */
    protected function wireAudit(): void
    {
        if (config('alamia360.persistence.driver') !== 'eloquent') {
            return;
        }

        $this->app->make(CapabilityExecutor::class)->auditUsing(
            fn (array $event) => $this->app->make(AuditService::class)->record($event)
        );
    }
}
