<?php

namespace Ngankt2\DbConfig;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Blade;
use Ngankt2\DbConfig\Commands\DbConfigCommand;
use Ngankt2\DbConfig\Testing\TestsDbConfig;
use Livewire\Features\SupportTesting\Testable;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class DbConfigServiceProvider extends PackageServiceProvider
{
    public static string $name = 'db-config';

    public static string $viewNamespace = 'db-config';

    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package->name(static::$name)
            ->hasCommands($this->getCommands())
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations()
                    ->askToStarRepoOnGitHub('ngankt2/db-config');
            });


        $configFileName = $package->shortName();

        if (file_exists($package->basePath("/../config/{$configFileName}.php"))) {
            $package->hasConfigFile();
        }

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if (file_exists($package->basePath('/../resources/lang'))) {
            $package->hasTranslations();
        }

        if (file_exists($package->basePath('/../resources/views'))) {
            $package->hasViews();
        }
    }

    public function packageRegistered(): void {}

    public function packageBooted(): void
    {
        // Handle Stubs
        if (app()->runningInConsole()) {
            foreach (app(Filesystem::class)->files(__DIR__ . '/../stubs/') as $file) {
                $this->publishes([
                    $file->getRealPath() => base_path("stubs/db-config/{$file->getFilename()}"),
                ], 'db-config-stubs');
            }
        }

        // Set the Blade directive to retrieve the settings
        Blade::directive('db_config', function ($expression) {
            return "<?php echo \Ngankt2\DbConfig\DbConfig::get($expression); ?>";
        });
    }

    protected function getAssetPackageName(): ?string
    {
        return 'ngankt2/db-config';
    }

    /**
     * @return array<class-string>
     */
    protected function getCommands(): array
    {
        return [
            DbConfigCommand::class,
        ];
    }

    /**
     * @return array<string>
     */
    protected function getMigrations(): array
    {
        return [
            'create_db_config_table',
        ];
    }
}
