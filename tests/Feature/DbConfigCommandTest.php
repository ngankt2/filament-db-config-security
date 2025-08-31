<?php

use Illuminate\Support\Facades\File;

it('creates a settings page class and view', function () {
    // Definisci i percorsi attesi
    $pageClassPath = app_path('Filament/Pages/WebsiteSettings.php');
    $viewPath = resource_path('views/filament/pages/website-settings.blade.php');

    // Assicurati che i file non esistano prima di eseguire il comando
    if (File::exists($pageClassPath)) {
        File::delete($pageClassPath);
    }
    if (File::exists($viewPath)) {
        File::delete($viewPath);
    }

    // Esegui il comando Artisan
    $this->artisan('make:db-config', [
        'name' => 'Website',
        '--no-interaction' => true, // Evita le domande interattive
    ])->assertSuccessful();

    // Verifica che il file della classe sia stato creato
    expect(File::exists($pageClassPath))->toBeTrue();

    // Verifica che il file della vista sia stato creato
    expect(File::exists($viewPath))->toBeTrue();

    // Pulisci i file creati
    File::delete($pageClassPath);
    File::delete($viewPath);
});
