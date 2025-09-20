<?php

namespace Ngankt2\DbConfig\Commands;

use Filament\Support\Commands\Concerns\CanAskForViewLocation;
use Filament\Support\Commands\Concerns\CanManipulateFiles;
use Filament\Support\Commands\Concerns\HasCluster;
use Filament\Support\Commands\Concerns\HasClusterPagesLocation;
use Filament\Support\Commands\Concerns\HasPanel;
use Filament\Support\Commands\Exceptions\FailureCommandOutput;
use Filament\Support\Commands\Concerns\CanOpenUrlInBrowser;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Pluralizer;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use ReflectionClass;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\search;
use function Laravel\Prompts\text;

#[AsCommand(name: 'make:db-config', aliases: ['db-config'])]
class DbConfigCommand extends Command
{
    use CanAskForViewLocation;
    use CanManipulateFiles;
    use HasCluster;
    use HasClusterPagesLocation;
    use HasPanel;
    use CanOpenUrlInBrowser;

    protected $description
        = 'Create a new Filament settings Page class and its Blade view. '
        . 'Usage: php artisan make:db-config [name?] [panel?] [--cluster=] — generates '
        . 'app/Filament/{Panel}/Pages/{Name}Settings.php or app/Filament/{Cluster}/Pages/{Name}Settings.php. '
        . 'If arguments are not provided, you will be prompted interactively. '
        . 'Existing files will not be overwritten unless --force is used.';

    /**
     * @var array<string>
     */
    protected $aliases = ['db-config'];

    /**
     * @var class-string
     */
    protected string $fqn;

    protected string $fqnEnd;

    protected ?string $view = null;

    protected ?string $viewPath = null;

    protected string $pagesNamespace;

    protected string $pagesDirectory;

    /**
     * Filesystem instance
     */
    protected Filesystem $files;

    /**
     * Create a new command instance.
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();

        $this->files = $files;
    }

    /**
     * Get the console command arguments.
     *
     * @return array<InputArgument>
     */
    protected function getArguments(): array
    {
        return [
            new InputArgument(
                name: 'name',
                mode: InputArgument::OPTIONAL,
                description: 'The name of the settings page to generate, optionally prefixed with directories',
            ),
        ];
    }

    /**
     * Get the console command options.
     *
     * @return array<InputOption>
     */
    protected function getOptions(): array
    {
        return [
            new InputOption(
                name: 'panel',
                mode: InputOption::VALUE_OPTIONAL,
                description: 'The panel to create the settings page in',
            ),
            new InputOption(
                name: 'cluster',
                shortcut: 'C',
                mode: InputOption::VALUE_OPTIONAL,
                description: 'The cluster to create the settings page in',
            ),
            new InputOption(
                name: 'force',
                shortcut: 'F',
                mode: InputOption::VALUE_NONE,
                description: 'Overwrite the contents of the files if they already exist',
            ),
        ];
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        try {
            $this->configureFqnEnd();
            $this->configurePanel(question: 'Which panel would you like to create this settings page in?');
            $this->configureCluster();
            $this->configurePagesLocation();
            $this->configureLocation();

            $this->createSettingsPage();
            $this->createView();
        } catch (FailureCommandOutput) {
            return static::FAILURE;
        }

        $this->components->info("Filament settings page [{$this->fqn}] created successfully.");

        if (empty($this->panel->getPageNamespaces())) {
            $this->components->info('Make sure to register the settings page with [pages()] or discover it with [discoverPages()] in the panel service provider.');
        }

        $this->askToStar();

        return static::SUCCESS;
    }

    /**
     * Configure the fully qualified name (FQN) end for the settings page.
     */
    protected function configureFqnEnd(): void
    {
        $name = $this->argument('name') ?? text(
            label: 'What is the name of the settings page?',
            placeholder: 'E.g. Site, General, Mail',
            hint: 'This will generate a {Name}Settings page class.',
            required: true
        );

        // Remove trailing "settings" suffix (case-insensitive) and trim whitespace
        $name = (string) Str::of($name)
            ->replaceMatches('/settings$/i', '')
            ->trim();

        $this->fqnEnd = (string) Str::of($name)
            ->trim('/')
            ->trim('\\')
            ->trim(' ')
            ->studly()
            ->append('Settings')
            ->replace('/', '\\');
    }

    /**
     * Configure the cluster for the settings page.
     */
    protected function configureCluster(): void
    {
        $this->configureClusterFqn(
            initialQuestion: 'Would you like to create this settings page in a cluster?',
            question: 'Which cluster would you like to create this settings page in?',
        );

        if (blank($this->clusterFqn)) {
            return;
        }

        $this->configureClusterPagesLocation();
    }

    /**
     * Configure the pages location for the settings page.
     */
    protected function configurePagesLocation(): void
    {
        if (filled($this->clusterFqn)) {
            return;
        }

        $directories = $this->panel->getPageDirectories();
        $namespaces  = $this->panel->getPageNamespaces();

        foreach ($directories as $index => $directory) {
            if (Str::of($directory)->startsWith(base_path('vendor'))) {
                unset($directories[$index]);
                unset($namespaces[$index]);
            }
        }

        if (count($namespaces) < 2) {
            $this->pagesNamespace = (Arr::first($namespaces) ?? app()->getNamespace() . 'Filament\\Pages');
            $this->pagesDirectory = (Arr::first($directories) ?? app_path('Filament/Pages/'));

            return;
        }

        $keyedNamespaces = array_combine(
            $namespaces,
            $namespaces,
        );

        $this->pagesNamespace = search(
            label: 'Which namespace would you like to create this settings page in?',
            options: function (?string $search) use ($keyedNamespaces): array {
                if (blank($search)) {
                    return $keyedNamespaces;
                }

                $search = Str::of($search)->trim()->replace(['\\', '/'], '');

                return array_filter($keyedNamespaces, fn(string $namespace): bool => Str::of($namespace)->replace(['\\', '/'], '')->contains($search, ignoreCase: true));
            },
        );
        $this->pagesDirectory = $directories[array_search($this->pagesNamespace, $namespaces)];
    }

    /**
     * Configure the location and view for the settings page.
     */
    protected function configureLocation(): void
    {
        $this->fqn = $this->pagesNamespace . '\\' . $this->fqnEnd;

        $viewString = Str::of($this->fqn)
            ->whenContains(
                'Filament\\',
                fn(Stringable $fqn) => $fqn->after('Filament\\')->prepend('filament/'),
                fn(Stringable $fqn) => $fqn->replaceFirst(app()->getNamespace(), ''),
            )
            ->replace('\\', '/')
            ->explode('/')
            ->map(fn(string $part) => Str::kebab($part))
            ->implode('.');

        $viewString = 'db-config.' . $this->panel->getId() . '.' . Str::kebab($this->fqnEnd);

        $useCustomView = confirm(
            label: 'Would you like to use a custom view?',
            default: true,
        );

        if ($useCustomView) {
            [
                $this->view,
                $this->viewPath,
            ] = $this->askForViewLocation(
                view: $viewString,
                question: 'Where would you like to create the Blade view for the settings page?',
                defaultNamespace: 'filament.config-pages',
            );
        } else {
            $this->view     = null;
            $this->viewPath = null;
        }
    }

    /**
     * Create the settings page class file.
     */
    protected function createSettingsPage(): void
    {
        $path = (string) Str::of("{$this->pagesDirectory}\\{$this->fqnEnd}.php")
            ->replace('\\', '/')
            ->replace('//', '/');

        if (!$this->option('force') && $this->checkForCollision($path)) {
            throw new FailureCommandOutput;
        }

        $contents = $this->getStubContents($this->getStubPath(), $this->getStubVariables());

        if ($contents === false) {
            $this->warn("Could not build source file contents for {$path}");
            throw new FailureCommandOutput;
        }

        $this->writeFile($path, $contents);
    }

    /**
     * Create the Blade view file for the settings page.
     */
    protected function createView(): void
    {
        if (blank($this->view)) {
            return;
        }

        if (!$this->option('force') && $this->checkForCollision($this->viewPath)) {
            throw new FailureCommandOutput;
        }

        $this->copyStubToApp('view', $this->viewPath);
    }

    /**
     * Return the stub file path.
     *
     * @return string
     */
    public function getStubPath(): string
    {
        return __DIR__ . '/../../stubs/page.stub';
    }

    /**
     * Map the stub variables present in stub to their values.
     *
     * @return array<string, string>
     */
    public function getStubVariables(): array
    {
        $name = Str::of($this->fqnEnd)->beforeLast('Settings')->trim();
        $singularClassName = Pluralizer::singular($name);

        $variables = [
            'TITLE'        => Str::headline($singularClassName),
            'NAMESPACE'    => $this->pagesNamespace,
            'CLASS_NAME'   => $this->fqnEnd,
            'SETTING_NAME' => Str::of($singularClassName)->headline()->lower()->slug(),
        ];

        // Add view variable to the stub (assumes stub has placeholder $VIEW_LINE$)
        // If no view is specified, use the default view or allow users to add it later
        $variables['VIEW_LINE'] = $this->view
            ? "protected string \$view = '{$this->view}';\n"
            : "protected string \$view = 'db-config::settings-base'; // Change this if you want to use a custom view\n";

        // Add cluster variable to the stub (assumes stub has placeholder $CLUSTER_LINE$)
        // If a cluster is specified, add the $cluster property with the full namespace
        $variables['CLUSTER_LINE'] = filled($this->clusterFqn)
            ? "protected static ?string \$cluster = \\{$this->clusterFqn}::class;\n"
            : '';

        return $variables;
    }

    /**
     * Replace the stub variables with their desired values.
     *
     * @param array<string, string> $stubVariables
     * @return string|false
     */
    public function getStubContents(string $stub, array $stubVariables = []): string|false
    {
        $contents = file_get_contents($stub);

        if ($contents === false) {
            return false;
        }

        foreach ($stubVariables as $search => $replace) {
            $contents = str_replace('$' . $search . '$', $replace, $contents);
        }

        return $contents;
    }

    /**
     * Prompt the user to star the GitHub repository.
     */
    protected function askToStar(): void
    {
        if ($this->option('no-interaction')) {
            return;
        }

        if (!confirm(
            label: '🚀 All set! If this tool saved you time, why not give it a ⭐ on GitHub? Your support helps keep it alive!',
            default: true,
        )) {
            return;
        }

        $this->openUrlInBrowser('https://github.com/ngankt2/filament-db-config-security');
    }
}
