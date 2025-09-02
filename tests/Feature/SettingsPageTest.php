<?php

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Inerba\DbConfig\AbstractPageSettings;
use Inerba\DbConfig\Facades\DbConfig;
use Livewire\Livewire;

beforeEach(function () {
    config(['session.driver' => 'array']);

    $migration = include __DIR__ . '/../../database/migrations/create_db_config_table.php.stub';
    $migration->up();

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

    // Usa l'helper di TestCase per condividere gli errori con la view in modo corretto.
    // Questo crea internamente un ViewErrorBag / MessageBag vuoto, evitando che Livewire
    // trovi null quando scrive nel bag.
    $this->withViewErrors(['default' => new MessageBag]);

    // SCENARIO 1: Nessun dato nel database.
    Livewire::test('settings-page-with-defaults')
        ->assertSet('data.name', 'Default Name')
        ->assertSet('data.value', 10);

    // Riapplica gli errori prima di creare la nuova istanza Livewire (difensivo in CI).
    $this->withViewErrors(['default' => new MessageBag]);

    // SCENARIO 2: Dati PARZIALI nel database.
    DbConfig::set('test-defaults.name', 'Database Name');

    Livewire::test('settings-page-with-defaults')
        ->assertSet('data.name', 'Database Name')
        ->assertSet('data.value', 10);
});
