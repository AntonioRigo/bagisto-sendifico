<?php

namespace Tapstar\Sendifico\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Tapstar\Sendifico\Console\Commands\RefreshTerritories;
use Tapstar\Sendifico\Console\Commands\SyncShipments;
use Tapstar\Sendifico\Listeners\OrderSaved;

class SendificoServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->registerConfig();
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadRoutesFrom(__DIR__.'/../Routes/admin-routes.php');

        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'sendifico');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'sendifico');

        Event::listen('bagisto.admin.sales.order.left_component.after', static function ($viewRenderEventManager) {
            $viewRenderEventManager->addTemplate('sendifico::admin.order-panel');
        });

        Event::listen('checkout.order.save.after', [OrderSaved::class, 'handle']);

        $this->registerCommands();
    }

    /**
     * Merge the carrier, admin settings and ACL configuration.
     */
    protected function registerConfig(): void
    {
        $this->mergeConfigFrom(
            dirname(__DIR__).'/Config/carriers.php', 'carriers'
        );

        $this->mergeConfigFrom(
            dirname(__DIR__).'/Config/system.php', 'core'
        );

        $this->mergeConfigFrom(
            dirname(__DIR__).'/Config/acl.php', 'acl'
        );
    }

    /**
     * Register the console commands and their schedule.
     */
    protected function registerCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            RefreshTerritories::class,
            SyncShipments::class,
        ]);

        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule) {
            $schedule->command('sendifico:territories')->weekly();

            $schedule->command('sendifico:sync')->everyFourHours();
        });
    }
}
