<?php

namespace Inerba\DbConfig\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\SpatieLaravelSettingsPluginServiceProvider;
use Filament\SpatieLaravelTranslatablePluginServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Inerba\DbConfig\DbConfigServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // Factory::guessFactoryNamesUsing(
        //     fn (string $modelName) => 'Inerba\\DbConfig\\Database\\Factories\\' . class_basename($modelName) . 'Factory'
        // );

        // Eseguiamo la migrazione del pacchetto prima di ogni test.
        // Questo assicura un database pulito per ogni scenario.
        $migration = include __DIR__ . '/../database/migrations/create_db_config_table.php.stub';
        $migration->up();
    }

    protected function getPackageProviders($app)
    {
        return [
            // ActionsServiceProvider::class,
            // BladeCaptureDirectiveServiceProvider::class,
            // BladeHeroiconsServiceProvider::class,
            // BladeIconsServiceProvider::class,
            // FilamentServiceProvider::class,
            // FormsServiceProvider::class,
            // InfolistsServiceProvider::class,
            // LivewireServiceProvider::class,
            // NotificationsServiceProvider::class,
            // SpatieLaravelSettingsPluginServiceProvider::class,
            // SpatieLaravelTranslatablePluginServiceProvider::class,
            // SupportServiceProvider::class,
            // TablesServiceProvider::class,
            // WidgetsServiceProvider::class,
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
    }
}
