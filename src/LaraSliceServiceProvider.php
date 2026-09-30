<?php

namespace LaraSlice;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use LaraSlice\Core\Discovery\SliceManager;
use LaraSlice\Core\Ai\AiEngine;
use LaraSlice\Core\Ai\McpServer;
use LaraSlice\Commands\SliceMakeCommand;
use LaraSlice\Commands\SliceListCommand;
use LaraSlice\Commands\SliceExportFlutterCommand;
use LaraSlice\Commands\SliceAiCommand;
use LaraSlice\Commands\BlueprintValidateCommand;
use LaraSlice\Commands\BlueprintPlanCommand;
use LaraSlice\Commands\BlueprintApplyCommand;
use LaraSlice\Blueprint\BlueprintStudioController;
use LaraSlice\Wizard\WizardController;

class LaraSliceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // 1. Merge configuration
        $this->mergeConfigFrom(__DIR__ . '/../config/laraslice.php', 'laraslice');

        // 2. Register SliceManager Singleton
        $this->app->singleton(SliceManager::class, function ($app) {
            return new SliceManager($app);
        });

        // 3. Register AiEngine Singleton
        $this->app->singleton(AiEngine::class, function ($app) {
            return new AiEngine($app->make(SliceManager::class));
        });

        // 4. Register McpServer Singleton
        $this->app->singleton(McpServer::class, function ($app) {
            return new McpServer($app->make(AiEngine::class));
        });
    }

    public function boot(): void
    {
        // 0. Register BlatUI twMerge fallback macro for zero-lock-in component compatibility
        if (!\Illuminate\View\ComponentAttributeBag::hasMacro('twMerge')) {
            \Illuminate\View\ComponentAttributeBag::macro('twMerge', function (...$classes) {
                $merged = implode(' ', array_filter(array_map(function ($c) {
                    return is_array($c) ? implode(' ', array_filter($c)) : (string) $c;
                }, $classes)));
                return $this->class($merged);
            });
        }
        // 1. Publish Configuration & Migrations
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/laraslice.php' => config_path('laraslice.php'),
            ], 'laraslice-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'laraslice-migrations');

            // Register Console Commands
            $this->commands([
                SliceMakeCommand::class,
                \LaraSlice\Commands\SliceInstallCommand::class,
                \LaraSlice\Commands\SliceWizardCommand::class,
                \LaraSlice\Commands\SliceFieldCommand::class,
                \LaraSlice\Commands\SliceUiPruneCommand::class,
                SliceListCommand::class,
                \LaraSlice\Commands\SliceSeedCommand::class,
                \LaraSlice\Commands\SliceToggleCommand::class,
                \LaraSlice\Commands\SliceWipeCommand::class,
                \LaraSlice\Commands\SliceDestroyCommand::class,
                SliceExportFlutterCommand::class,
                SliceAiCommand::class,
                BlueprintValidateCommand::class,
                BlueprintPlanCommand::class,
                BlueprintApplyCommand::class,
                \LaraSlice\Console\Commands\SliceSyncCommand::class,
                \LaraSlice\Commands\SliceCacheCommand::class,
                \LaraSlice\Commands\SliceClearCommand::class,
                \LaraSlice\Commands\SlicePublishCommand::class,
            ]);
        }

        // 2. Load Core Migrations & Views
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/Wizard/views', 'laraslice');

        // 3. Register Wizard Routes
        if (config('laraslice.wizard.enabled', true)) {
            Route::prefix('laraslice/wizard')->middleware(config('laraslice.wizard.middleware', ['web', 'auth']))->group(function () {
                Route::get('/', [WizardController::class, 'show'])->name('laraslice.wizard');
                Route::get('/blueprint', [BlueprintStudioController::class, 'show'])->name('laraslice.wizard.blueprint');
                Route::post('/blueprint/plan', [BlueprintStudioController::class, 'plan'])->name('laraslice.wizard.blueprint.plan');
                Route::post('/blueprint/apply', [BlueprintStudioController::class, 'apply'])->name('laraslice.wizard.blueprint.apply');
                Route::post('/blueprint/migrate', [BlueprintStudioController::class, 'runMigrations'])->name('laraslice.wizard.blueprint.migrate');
                Route::post('/blueprint/introspect', [BlueprintStudioController::class, 'introspect'])->name('laraslice.wizard.blueprint.introspect');
                Route::post('/generate', [WizardController::class, 'generate'])->name('laraslice.wizard.generate');
                Route::post('/migrate', [WizardController::class, 'runMigration'])->name('laraslice.wizard.migrate');
                Route::get('/slices', [WizardController::class, 'listSlices'])->name('laraslice.wizard.slices');
                Route::get('/audit-logs', [WizardController::class, 'getAuditLogs'])->name('laraslice.wizard.audit_logs');
                Route::post('/add-field', [WizardController::class, 'addField'])->name('laraslice.wizard.add_field');
                Route::post('/add-fields-batch', [WizardController::class, 'addFieldsBatch'])->name('laraslice.wizard.add_fields_batch');
                Route::post('/add-child-table', [WizardController::class, 'addChildTable'])->name('laraslice.wizard.add_child_table');
                Route::post('/update-navigation', [WizardController::class, 'updateNavigation'])->name('laraslice.wizard.update_nav');
                Route::post('/rollback-version', [WizardController::class, 'rollbackVersion'])->name('laraslice.wizard.rollback_version');
                Route::post('/sync-fields', [WizardController::class, 'syncFields'])->name('laraslice.wizard.sync_fields');
                Route::post('/save-relationships', [WizardController::class, 'saveRelationships'])->name('laraslice.wizard.save_relationships');
                Route::post('/copilot/chat', [WizardController::class, 'copilotChat'])->name('laraslice.wizard.copilot_chat');
                Route::post('/batch-generate', [WizardController::class, 'batchGenerate'])->name('laraslice.wizard.batch_generate');
                Route::post('/domain-suite', [WizardController::class, 'generateDomainSuite'])->name('laraslice.wizard.domain_suite');
                Route::post('/toggle-slice', [WizardController::class, 'toggleSlice'])->name('laraslice.wizard.toggle_slice');
                Route::post('/toggle-domain', [WizardController::class, 'toggleDomain'])->name('laraslice.wizard.toggle_domain');
                Route::post('/seed-slice', [WizardController::class, 'seedSlice'])->name('laraslice.wizard.seed_slice');
                Route::post('/seed-domain', [WizardController::class, 'seedDomain'])->name('laraslice.wizard.seed_domain');
                Route::post('/wipe-slice', [WizardController::class, 'wipeSlice'])->name('laraslice.wizard.wipe_slice');
                Route::post('/wipe-domain', [WizardController::class, 'wipeDomain'])->name('laraslice.wizard.wipe_domain');
                Route::post('/destroy-slice', [WizardController::class, 'destroySlice'])->name('laraslice.wizard.destroy_slice');
                Route::post('/destroy-domain', [WizardController::class, 'destroyDomain'])->name('laraslice.wizard.destroy_domain');
            });
        }

        // 4. Register MCP (Model Context Protocol) Route for AI Agents (Cursor / Antigravity / Claude)
        if (config('laraslice.ai.mcp_server.enabled', true)) {
            Route::middleware(config('laraslice.ai.mcp_server.middleware', ['auth']))
                ->any(config('laraslice.ai.mcp_server.route', '/.well-known/mcp'), function (\Illuminate\Http\Request $request) {
                return app(McpServer::class)->handle($request);
            });
        }

        // 5. Discover and Boot All Installed Slices
        if (config('laraslice.auto_discovery', true)) {
            $this->app->make(SliceManager::class)->discover();
        }

        // 6. Share dynamic navigation data with all views for sidebar rendering
        $this->app->booted(function () {
            $manager = $this->app->make(SliceManager::class);
            view()->composer('*', function ($view) use ($manager) {
                if (! $view->offsetExists('laraslice_nav')) {
                    $view->with('laraslice_nav', $manager->getNavigableSlices());
                }
            });
        });

        // 7. Register Native Slice RBAC Gate Bridge
        \Illuminate\Support\Facades\Gate::before(function ($user, string $ability) {
            if (method_exists($user, 'hasRole') && $user->hasRole('super-admin')) {
                return true;
            }
            if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
                return true;
            }
            if (isset($user->email) && $user->email === config('laraslice.super_admin_email', 'admin@laraslice.com')) {
                return true;
            }
            if (isset($user->id)) {
                try {
                    if (\Illuminate\Support\Facades\Schema::hasTable('role_user') && \Illuminate\Support\Facades\Schema::hasTable('roles')) {
                        $isSuperAdmin = \Illuminate\Support\Facades\DB::table('role_user')
                            ->join('roles', 'role_user.role_id', '=', 'roles.id')
                            ->where('role_user.user_id', $user->id)
                            ->where(function ($q) {
                                $q->where('roles.slug', 'super-admin')
                                  ->orWhere('roles.id', 1);
                            })
                            ->exists();
                        if ($isSuperAdmin) {
                            return true;
                        }
                    }
                } catch (\Throwable $e) {}
            }
            if (method_exists($user, 'hasPermission')) {
                return $user->hasPermission($ability) ? true : null;
            }
            return null;
        });

        // 8. Register default dashboard route fallback if host application doesn't define one
        $this->app->booted(function () {
            if (! Route::has('dashboard')) {
                Route::middleware(['web'])->get('/dashboard', function () {
                    return redirect()->route('laraslice.wizard');
                })->name('dashboard');
            }
        });
    }
}
