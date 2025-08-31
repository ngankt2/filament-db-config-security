<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inerba\DbConfig\DbConfig;

beforeEach(function () {
    $migration = include __DIR__ . '/../../database/migrations/create_db_config_table.php.stub';
    $migration->up();
});

// Test per il set e get di un valore semplice
it('can set and get a simple value', function () {
    DbConfig::set('website.site_name', 'My Awesome Site');

    expect(DbConfig::get('website.site_name'))->toBe('My Awesome Site');
});

// Test per il valore di default
it('returns a default value if key does not exist', function () {
    $value = DbConfig::get('website.non_existent_key', 'Default Value');

    expect($value)->toBe('Default Value');
});

// Test per i valori annidati (nested)
it('can set and get a nested value', function () {
    DbConfig::set('contact.details', [
        'email' => 'test@example.com',
        'phone' => '1234567890',
    ]);

    expect(DbConfig::get('contact.details.email'))->toBe('test@example.com');
    expect(DbConfig::get('contact.details.phone'))->toBe('1234567890');
});

// Test per il recupero di un intero gruppo
it('can get an entire group', function () {
    DbConfig::set('website.site_name', 'My Site');
    DbConfig::set('website.tagline', 'The best site ever');

    $group = DbConfig::getGroup('website');

    expect($group)->toBe([
        'site_name' => 'My Site',
        'tagline' => 'The best site ever',
    ]);
});

// Test del Caching: verifica che la cache venga usata e pulita correttamente
it('caches values on get and forgets on set', function () {
    // 1. Imposta un valore iniziale e verificalo
    DbConfig::set('cache_test.key', 'initial_value');
    expect(DbConfig::get('cache_test.key'))->toBe('initial_value');

    // 2. Verifica che il valore sia in cache
    expect(Cache::has('db-config.cache_test.key'))->toBeTrue();
    expect(Cache::get('db-config.cache_test.key'))->toBe(['key' => 'initial_value']);

    // 3. Modifica il valore direttamente nel DB per assicurarsi che il test legga dalla cache
    DB::table('db_config')->where('group', 'cache_test')->where('key', 'key')->update(['settings' => json_encode('db_value')]);

    // 4. Leggendo di nuovo, dovremmo ottenere il valore in cache, non quello nel DB
    expect(DbConfig::get('cache_test.key'))->toBe('initial_value');

    // 5. Ora usiamo DbConfig::set() per aggiornare il valore. Questo dovrebbe pulire la cache.
    DbConfig::set('cache_test.key', 'new_value');

    // 6. Verifica che la cache sia stata aggiornata
    expect(Cache::has('db-config.cache_test.key'))->toBeFalse(); // `set` la rimuove, la `get` successiva la ripopola
    expect(DbConfig::get('cache_test.key'))->toBe('new_value');
    expect(Cache::has('db-config.cache_test.key'))->toBeTrue();
});

// Test per l'helper `db_config()`
it('provides a helper function', function () {
    DbConfig::set('helper.test', 'it works');
    expect(db_config('helper.test'))->toBe('it works');
});
