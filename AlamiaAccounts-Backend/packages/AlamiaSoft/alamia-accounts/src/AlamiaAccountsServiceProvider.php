<?php

namespace AlamiaSoft\AlamiaAccounts;

use Illuminate\Support\ServiceProvider;
use AlamiaSoft\AlamiaAccounts\Services\{AccountService, VoucherService, ReportService, CompanyService, CustomVoucherTypeService, VoucherNumberingService, SearchService, PrintService, AutomationService, PermissionService};

class AlamiaAccountsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register core services
        $this->app->singleton(CompanyService::class, function ($app) {
            return new CompanyService();
        });

        $this->app->singleton(AccountService::class, function ($app) {
            return new AccountService();
        });

        $this->app->singleton(VoucherService::class, function ($app) {
            return new VoucherService();
        });

        $this->app->singleton(ReportService::class, function ($app) {
            return new ReportService();
        });
        
        // Register new services
        $this->app->singleton(CustomVoucherTypeService::class, function ($app) {
            return new CustomVoucherTypeService();
        });
        
        $this->app->singleton(VoucherNumberingService::class, function ($app) {
            return new VoucherNumberingService();
        });
        
        $this->app->singleton(SearchService::class, function ($app) {
            return new SearchService();
        });
        
        $this->app->singleton(PrintService::class, function ($app) {
            return new PrintService();
        });
        
        $this->app->singleton(AutomationService::class, function ($app) {
            return new AutomationService();
        });
        
        $this->app->singleton(PermissionService::class, function ($app) {
            return new PermissionService();
        });

        // Merge config
        $this->mergeConfigFrom(__DIR__.'/../config/alamia-accounts.php', 'alamia-accounts');
    }

    public function boot()
    {
        \Illuminate\Support\Facades\Route::prefix('api')
            ->middleware('api')
            ->group(function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
            });

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/alamia-accounts.php' => config_path('alamia-accounts.php'),
            ], 'alamia-accounts-config');
        }
    }
}
