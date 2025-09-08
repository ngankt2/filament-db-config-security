<?php

use Illuminate\Support\Facades\File;

it('does not duplicate the Settings suffix when user provides it', function () {
    // Definisci i percorsi attesi
    $pageClassPath = app_path('Filament/Pages/WebsiteSettings.php');
    $viewPath = resource_path('views/filament/pages/website-settings.blade.php');

    // Pulisci eventuali file rimasti da altri test
    if (File::exists($pageClassPath)) {
        File::delete($pageClassPath);
    }
    if (File::exists($viewPath)) {
        File::delete($viewPath);
    }

    // Esegui il comando fornendo un nome che già contiene il suffisso 'Settings'
    $this->artisan('make:db-config', [
        'name' => 'WebsiteSettings',
        '--no-interaction' => true,
    ])->assertSuccessful();

    // Verifica che il file della classe sia stato creato correttamente (senza duplicare 'Settings')
    expect(File::exists($pageClassPath))->toBeTrue();

    // Verifica che il file della vista sia stato creato
    expect(File::exists($viewPath))->toBeTrue();

    // Pulisci i file creati
    File::delete($pageClassPath);
    File::delete($viewPath);
});
