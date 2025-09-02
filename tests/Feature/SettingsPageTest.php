<?php

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Inerba\DbConfig\AbstractPageSettings;
use Inerba\DbConfig\Facades\DbConfig;
use Livewire\Livewire;

beforeEach(function () {
    $migration = include __DIR__ . '/../../database/migrations/create_db_config_table.php.stub';
    $migration->up();

    config(['session.driver' => 'array']);
});

class SettingsPageWithDefaults extends AbstractPageSettings
{
    protected function settingName(): string
    {
        return 'test-defaults';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name'),
            TextInput::make('value')->numeric(),
        ]);
    }

    public function getDefaultData(): array
    {
        return [
            'name' => 'Default Name',
            'value' => 10,
        ];
    }
}

it('loads default data and merges correctly with database values', function () {
    Livewire::component('settings-page-with-defaults', SettingsPageWithDefaults::class);

    // Share a proper ViewErrorBag with the view (avoid session-based initialization in CI)
    $errors = new ViewErrorBag;
    $errors->put('default', new MessageBag);
    app('view')->share('errors', $errors);
    // Alternatively: $this->withViewErrors([]);  // uses Laravel's testing helper

    // SCENARIO 1: Nessun dato nel database.
    Livewire::test('settings-page-with-defaults')
        ->assertSet('data.name', 'Default Name')
        ->assertSet('data.value', 10);

    // Re-share errors before a new Livewire test instance (defensive in CI)
    $errors = new ViewErrorBag;
    $errors->put('default', new MessageBag);
    app('view')->share('errors', $errors);

    // SCENARIO 2: Dati PARZIALI nel database.
    DbConfig::set('test-defaults.name', 'Database Name');

    Livewire::test('settings-page-with-defaults')
        ->assertSet('data.name', 'Database Name')
        ->assertSet('data.value', 10);
});
