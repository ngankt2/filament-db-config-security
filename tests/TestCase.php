<?php

namespace Inerba\DbConfig\Tests;

// CORE LARAVEL/TESTBENCH
use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
// FACADES and HELPERS
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
// SERVICE PROVIDERS
use Filament\Facades\Filament;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Panel;
use Filament\PanelRegistry;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Inerba\DbConfig\DbConfigServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider; // Your package's provider

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
        // $migration = include __DIR__ . '/../database/migrations/create_db_config_table.php.stub';
        // $migration->up();

        // Register a default Filament panel so Filament pages can render in tests.
        $this->app->make(PanelRegistry::class)->register(
            Panel::make()->id('default')->default(true),
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            ActionsServiceProvider::class,
            BladeCaptureDirectiveServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeIconsServiceProvider::class,
            FilamentServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            LivewireServiceProvider::class,
            NotificationsServiceProvider::class,
            SupportServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            DbConfigServiceProvider::class,
        ];
    }

    /**
     * Definisci l'ambiente di test.
     *
     * @param  \Illuminate\Foundation\Application  $app
     */
    public function getEnvironmentSetUp($app)
    {
        // Usiamo un database SQLite in memoria: è velocissimo e non lascia file spazzatura.
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Set a dummy application key so encryption / cookie signing works in tests.
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
    }
}
