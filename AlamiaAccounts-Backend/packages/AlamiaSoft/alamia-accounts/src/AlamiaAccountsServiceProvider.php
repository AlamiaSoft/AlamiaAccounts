<?php

namespace AlamiaSoft\AlamiaAccounts;

use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\Router;
use AlamiaSoft\AlamiaAccounts\Services\{
    AccountService,
    VoucherService,
    ReportService,
    CompanyService,
    CustomVoucherTypeService,
    VoucherNumberingService,
    SearchService,
    PrintService,
    AutomationService,
    PermissionService,
    SalesIntegrationService,
    AccountingDiagnosticService,
    AccountingIntegrityGuard,
    OpeningBalanceService,
    PeriodService
};
use AlamiaSoft\AlamiaAccounts\Copilot\{
    CopilotService,
    ConversationContextService,
    CopilotDiagnosticsService,
    GuidanceKnowledgeService,
    IntentClassifierService,
    ParlantClient
};
use AlamiaSoft\AlamiaAccounts\Http\Middleware\AuthenticateSalesOrSanctum;
use AlamiaSoft\AlamiaAccounts\Console\Commands\SyncCopilotKnowledgeCommand;

class AlamiaAccountsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Core accounting services
        $this->app->singleton(CompanyService::class);
        $this->app->singleton(AccountService::class);
        $this->app->singleton(VoucherService::class);
        $this->app->singleton(ReportService::class);
        $this->app->singleton(OpeningBalanceService::class);
        $this->app->singleton(PeriodService::class);
        
        // Extended voucher & automation services
        $this->app->singleton(CustomVoucherTypeService::class);
        $this->app->singleton(VoucherNumberingService::class);
        $this->app->singleton(PrintService::class);
        $this->app->singleton(AutomationService::class);
        $this->app->singleton(PermissionService::class);

        // Sales POS integration & forensic diagnostics
        $this->app->singleton(SalesIntegrationService::class);
        $this->app->singleton(AccountingDiagnosticService::class);
        $this->app->singleton(AccountingIntegrityGuard::class);
        $this->app->singleton(SearchService::class);

        // AI Copilot Services
        $this->app->singleton(ConversationContextService::class);
        $this->app->singleton(CopilotDiagnosticsService::class);
        $this->app->singleton(GuidanceKnowledgeService::class);
        $this->app->singleton(IntentClassifierService::class);
        $this->app->singleton(ParlantClient::class);
        $this->app->singleton(CopilotService::class);

        // Merge config
        $this->mergeConfigFrom(__DIR__.'/../config/alamia-accounts.php', 'alamia-accounts');
    }

    public function boot(Router $router): void
    {
        // Register API routes
        \Illuminate\Support\Facades\Route::prefix('api')
            ->middleware('api')
            ->group(function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
            });

        // Register turnkey web route for embedded SPA UI
        \Illuminate\Support\Facades\Route::middleware('web')->get('/alamia-accounts/{any?}', function (?string $any = null) {
            if ($any === 'login' || $any === 'login/') {
                $loginPath = public_path('vendor/alamia-accounts/login.html');
                if (file_exists($loginPath)) {
                    return response()->file($loginPath);
                }
            }
            $distPath = public_path('vendor/alamia-accounts/index.html');
            if (file_exists($distPath)) {
                return response()->file($distPath);
            }
            $pkgPath = __DIR__.'/../dist/index.html';
            if (file_exists($pkgPath)) {
                return response()->file($pkgPath);
            }
            return response("Alamia Accounts UI bundle not published. Run 'php artisan vendor:publish --tag=alamia-accounts-ui'", 404);
        })->where('any', '.*');

        // Route static assets for embedded Next.js UI
        \Illuminate\Support\Facades\Route::get('/_next/{any}', function (string $any) {
            $path = public_path('vendor/alamia-accounts/_next/' . $any);
            if (file_exists($path)) {
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $mime = match ($ext) {
                    'js' => 'application/javascript',
                    'css' => 'text/css',
                    'json' => 'application/json',
                    'woff2' => 'font/woff2',
                    'woff' => 'font/woff',
                    'png' => 'image/png',
                    'jpg', 'jpeg' => 'image/jpeg',
                    'svg' => 'image/svg+xml',
                    default => 'application/octet-stream',
                };
                return response()->file($path, ['Content-Type' => $mime]);
            }
            return response('Asset not found', 404);
        })->where('any', '.*');

        // Route static icons/logos requested from root by embedded UI
        \Illuminate\Support\Facades\Route::get('/{asset}', function (string $asset) {
            $allowed = ['alamia-logo.png', 'apple-icon.png', 'favicon.ico', 'icon-dark-32x32.png', 'icon-light-32x32.png', 'placeholder-logo.png', 'placeholder-logo.svg', 'placeholder-user.jpg', 'placeholder.jpg', 'placeholder.svg'];
            if (in_array($asset, $allowed, true)) {
                $path = public_path('vendor/alamia-accounts/' . $asset);
                if (file_exists($path)) {
                    return response()->file($path);
                }
            }
            return response('Not found', 404);
        })->where('asset', '.*\.(png|svg|ico|jpg|jpeg)');

        // Load database migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Load package Blade views
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'alamia-accounts');

        // Register route middleware alias
        $router->aliasMiddleware('sales.auth', AuthenticateSalesOrSanctum::class);

        // Register console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncCopilotKnowledgeCommand::class,
            ]);

            $this->publishes([
                __DIR__ . '/../config/alamia-accounts.php' => config_path('alamia-accounts.php'),
            ], 'alamia-accounts-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'alamia-accounts-migrations');

            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/alamia-accounts'),
            ], 'alamia-accounts-views');

            $this->publishes([
                __DIR__ . '/../dist' => public_path('vendor/alamia-accounts'),
            ], 'alamia-accounts-ui');
        }
    }
}
