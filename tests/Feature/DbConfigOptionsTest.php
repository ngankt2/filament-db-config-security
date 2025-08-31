<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inerba\DbConfig\DbConfig;

// Test per il nome della tabella personalizzato
it('uses a custom table name when configured', function () {
    $customTableName = 'my_awesome_settings';

    // 1. Imposta il nome della tabella personalizzato nella configurazione PRIMA di creare la tabella
    config(['db-config.table_name' => $customTableName]);

    // 2. Assicurati che nessuna delle due tabelle esista inizialmente
    expect(Schema::hasTable('db_config'))->toBeFalse();
    expect(Schema::hasTable($customTableName))->toBeFalse();

    // 3. Esegui la migrazione, che ora dovrebbe usare il nome custom
    $migration = include __DIR__ . '/../../database/migrations/create_db_config_table.php.stub';
    $migration->up();

    // 4. Verifica che sia stata creata la tabella custom, non quella di default
    expect(Schema::hasTable($customTableName))->toBeTrue();
    expect(Schema::hasTable('db_config'))->toBeFalse();

    // 5. Scrivi un valore
    DbConfig::set('app.name', 'My Custom App');

    // 4. Verifica che il valore sia stato scritto nella tabella giusta
    $record = DB::table($customTableName)->where('group', 'app')->where('key', 'name')->first();
    expect($record->settings)->toBe('"My Custom App"');

    // 5. Leggi il valore per confermare
    expect(DbConfig::get('app.name'))->toBe('My Custom App');
});

// Test per il prefisso della cache personalizzato
it('uses a custom cache prefix when configured', function () {
    // Setup: crea la tabella standard per questo test
    $migration = include __DIR__ . '/../../database/migrations/create_db_config_table.php.stub';
    $migration->up();

    // 1. Imposta un prefisso custom
    $customPrefix = 'my-app-settings';
    config(['db-config.cache.prefix' => $customPrefix]);

    // 2. Scrivi e leggi un valore per popopolare la cache
    DbConfig::set('cache.test', 'value');
    DbConfig::get('cache.test');

    // 3. Verifica che la chiave in cache usi il prefisso custom
    $expectedCacheKey = "{$customPrefix}.cache.test";
    expect(Cache::has($expectedCacheKey))->toBeTrue();

    // 4. Verifica che NON usi il prefisso di default
    $defaultCacheKey = 'db-config.cache.test';
    expect(Cache::has($defaultCacheKey))->toBeFalse();
});

// Test per la cache con TTL (a tempo)
it('uses remember instead of rememberForever when ttl is set', function () {
    // Setup: crea la tabella standard per questo test
    $migration = include __DIR__ . '/../../database/migrations/create_db_config_table.php.stub';
    $migration->up();

    // 1. Imposta un TTL in minuti
    config(['db-config.cache.ttl' => 10]);

    // 2. "Spia" la Facade della Cache per vedere quali metodi vengono chiamati
    Cache::spy();

    // 3. Esegui una `get` per attivare la logica della cache
    DbConfig::get('ttl.test', 'default');

    // 4. Verifica che sia stato chiamato il metodo giusto
    // Ci aspettiamo che `remember` sia stato chiamato una volta
    Cache::shouldHaveReceived('remember')->once();

    // E che `rememberForever` NON sia stato chiamato
    Cache::shouldNotHaveReceived('rememberForever');
});

// Test per la cache infinita (comportamento di default)
it('uses rememberForever when ttl is null', function () {
    // Setup: crea la tabella standard per questo test
    $migration = include __DIR__ . '/../../database/migrations/create_db_config_table.php.stub';
    $migration->up();

    // 1. Assicurati che il TTL sia null (default)
    config(['db-config.cache.ttl' => null]);

    // 2. Spia la Facade
    Cache::spy();

    // 3. Esegui una `get`
    DbConfig::get('forever.test', 'default');

    // 4. Verifica che sia stato chiamato il metodo giusto
    Cache::shouldHaveReceived('rememberForever')->once();
    Cache::shouldNotHaveReceived('remember');
});
