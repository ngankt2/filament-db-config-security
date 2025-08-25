# DB Config – Lightweight settings & content manager for Filament

DB Config is a Filament plugin that provides a simple, database-backed key/value store for **application settings** and **editable content**.  
It’s ideal both for storing configuration (like site name, contact info, labels) and for managing page sections (homepage hero, landing blocks, about text, etc.) without the need for a full CMS.

- 🔑 Store application settings in a simple key/value table  
- 📝 Manage editable content (homepages, landing pages, about sections)  
- ⚡ Use any Filament form fields or layouts — including third-party ones  
- 🗄️ Transparent caching, no extra boilerplate, zero external deps  

It provides a clean set of simple helpers for reading and writing values, with transparent caching under the hood, and persists data as JSON in a dedicated table.  
It is framework-friendly and requires no custom Eloquent models in your app.

<img width="1280" height="640" alt="filament-db-config" src="https://raw.githubusercontent.com/inerba/filament-db-config/refs/heads/main/screenshot.jpg" />

> You may use **any Filament form fields or layout components - including third-party ones -** to build your settings and content pages, giving you full flexibility in how data is structured and edited.

<div class="filament-hidden">
<b>Table of Contents</b>

- [DB Config – Lightweight settings \& content manager for Filament](#db-config--lightweight-settings--content-manager-for-filament)
  - [Why use DB Config when Spatie Settings already exists?](#why-use-db-config-when-spatie-settings-already-exists)
  - [Requirements](#requirements)
  - [Installation](#installation)
  - [Scaffolding \& Filament integration](#scaffolding--filament-integration)
  - [Read \& write values](#read--write-values)
    - [Read a value (helper):](#read-a-value-helper)
    - [Blade directive](#blade-directive)
    - [Read a value (class):](#read-a-value-class)
    - [Write a value:](#write-a-value)
    - [Read an entire group as associative array:](#read-an-entire-group-as-associative-array)
    - [Facade (optional):](#facade-optional)
  - [How it works](#how-it-works)
  - [Database schema](#database-schema)
  - [Working with nested values](#working-with-nested-values)
  - [Caching behavior](#caching-behavior)
  - [Return values and defaults](#return-values-and-defaults)
  - [Database engines](#database-engines)
  - [Security considerations](#security-considerations)
  - [Versioning](#versioning)
  - [License](#license)
</div>

## Why use DB Config when Spatie Settings already exists?

Both [DB Config](https://github.com/inerba/filament-db-config) and the official [Spatie Laravel Settings Plugin](https://github.com/filamentphp/spatie-laravel-settings-plugin) solve a similar problem - managing application settings in Laravel + Filament - but they take very different approaches.  
Spatie Settings focuses on **strict typing, validation, and integration with your domain logic**, while DB Config is designed to be **lightweight, flexible, and quick to set up**, even for editable content blocks.

The table below highlights the key differences so you can choose the right tool for your project:

| Feature           | DB Config                                                                | Spatie Laravel Settings Plugin                                                      |
| ----------------- | ------------------------------------------------------------------------ | ----------------------------------------------------------------------------------- |
| **Setup**         | Ready to use, no extra classes or migrations                             | Requires a dedicated `Settings` class for each group, plus migration to register it |
| **Data storage**  | Single `db_config` table with JSON values                                | Each group stored as its own settings record (linked to its Settings class)         |
| **Boilerplate**   | None required                                                            | A new PHP class must be created for every settings group                            |
| **Access**        | Dot notation, supports nested keys in JSON                               | Strongly typed properties defined in the Settings class                             |
| **Cache**         | Built-in, refreshed automatically on write                               | Built-in, but usually configured explicitly                                         |
| **Ideal for**     | Application settings and editable page content (homepage, blocks, texts) | Strictly typed, validated settings tightly bound to app logic                       |
| **Content usage** | Can store full page sections (homepage, landing, about, etc.)            | Not designed for CMS-like use                                                       |
| **Dependencies**  | No external deps                                                         | Requires `spatie/laravel-settings`                                                  |

Choose **DB Config** if you want:

* A **lightweight key/value system** for both settings and content.
* Minimal setup, no boilerplate code.
* Flexibility to manage simple settings and even **page content** directly in Filament.

Choose **Spatie Laravel Settings Plugin** if you need **strict typing, validation, and DTOs** as part of your domain logic.

## Requirements

- PHP version supported by your Laravel installation
- Laravel 12
- A database engine with JSON support (MySQL 5.7+, MariaDB 10.2.7+, PostgreSQL, SQLite recent versions)
- Filament 4

## Installation

Install the package via Composer (choose the version matching your Laravel version):

```bash
composer require inerba/filament-db-config
```

Publish and run the migration:

```bash
php artisan vendor:publish --tag="db-config-migrations"
php artisan migrate
```

This creates a `db_config` table used to store your settings.

## Scaffolding & Filament integration

DB Config ships with an Artisan generator and an abstract Page class to quickly scaffold Filament settings pages.

**Generate a settings page**

```bash
php artisan make:db-config {name} {panel?}
```

Parameters:

- `name`: the settings group name (e.g. `website`). It is used to generate the view name and the class name (singular, capitalized).
- `panel` (optional): the Filament panel to create the page in (e.g. `Admin`). If omitted the default panel is used.

Examples:

```bash
php artisan make:db-config website            # default panel
php artisan make:db-config website admin      # specific panel (e.g. Admin)
```

What is generated:

- A Page class at `app/Filament/{Panel}/Pages/{Name}Settings.php` (the class name is the singular form of `{name}` + `Settings`, e.g. `WebsiteSettings.php`).
- A Blade view at `resources/views/filament/config-pages/{slug-name}-settings.blade.php` (the view name is a slugified version of the `name` with a `-settings` suffix).

Behavior:

- The command does not overwrite existing files: if the class or the view already exist it will warn and leave the files intact.
- Names are normalized: the class uses the singular form of the provided name, the view is slugified (spaces and special characters are converted).

Note: the generated class extends `Inerba\DbConfig\AbstractPageSettings` and the view is placed under `resources/views/filament/config-pages/`.

Page lifecycle and saving:

- On `mount()`, the page loads all settings for the given group (defined by `settingName()`) via `DbConfig::getGroup()` and fills the page content state.
- A built-in header action “Save” persists the current state by calling `DbConfig::set("{group}.{key}", $value)` for each top-level key present in the page content.

Defining the page content:

- Implement `protected function settingName(): string` to define the group name (e.g. `website`).
- Implement `public function content(Schema $schema): Schema` and return your content schema.
- Set `->statePath('data')` so the page state is bound to the `$data` property and saved correctly.

Example page content (Filament schema):

```php
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema; // or import the correct Schema depending on your setup

public function content(Schema $schema): Schema
{
    return $schema
        ->components([
            TextInput::make('site_name')->required(),
            // ... other inputs
        ])
        ->statePath('data');
}
```

## Read & write values

The simplest way to manage values is through the **Filament pages scaffolded by DB Config**:  
you can define the fields you need (text inputs, toggles, repeaters, rich text, even third-party components) and edit them directly in the admin panel.  
All changes are saved automatically in the `db_config` table and cached for fast access.

For programmatic access, the package also provides simple helpers and static methods:

### Read a value (helper):

```php
db_config('website.site_name', 'Default Name');
```

### Blade directive

You can also access values directly inside Blade templates:

```blade
@db_config('website.site_name', 'Default Name')
```

### Read a value (class):

```php
\Inerba\DbConfig\DbConfig::get('website.site_name', 'Default Name');
```

### Write a value:

```php
\Inerba\DbConfig\DbConfig::set('website.site_name', 'Acme Inc.');
```

### Read an entire group as associative array:

```php
\Inerba\DbConfig\DbConfig::getGroup('website');
// => [ 'site_name' => 'Acme Inc.', 'contact' => ['email' => 'info@acme.test'] ]
```

### Facade (optional):

```php
\Inerba\DbConfig\Facades\DbConfig::get('website.site_name');
```
> Note: these values are not part of Laravel’s config() cache.
Always use db_config() or @db_config instead of config().

> The `db_config()` helper is auto-registered by the package and is the recommended way to read values in application code.

## How it works

Settings are organized by a two-part key: `group.setting`, with optional nested sub-keys (e.g. `group.setting.nested.key`).

Under the hood:

- Settings are stored in a single row per `(group, key)` with the JSON payload in the `settings` column.
- Reads are cached forever under the cache key `db-config.{group}.{setting}`.
- Writes clear the corresponding cache entry to keep reads fresh.

## Database schema

The `db_config` table contains:

- `id` (bigint, primary key)
- `group` (string)
- `key` (string)
- `settings` (json, nullable)
- `created_at`, `updated_at` (timestamps)

There is a unique index on (`group`, `key`). Timestamps are present but not used by the package logic and may remain null depending on your database defaults.

## Working with nested values

DB Config uses a `group.setting` format for keys, with optional nested sub-keys resolved from JSON.

- The first segment is the **group**  
- The second is the **top-level key**  
- Any remaining segments are treated as nested paths inside the JSON value  

Example:

```php
// Store a nested structure
\Inerba\DbConfig\DbConfig::set('profile.preferences', [
    'theme' => 'dark',
    'notifications' => ['email' => true, 'sms' => false],
]);

// Read a nested value with default
db_config('profile.preferences.theme', 'light'); // 'dark'

// Read a missing nested value
db_config('profile.preferences.timezone', 'UTC'); // 'UTC'
```

## Caching behavior

- Reads are cached forever per `(group, setting)` to minimize database traffic.
- `DbConfig::set()` automatically clears the cache for the affected `(group, setting)` pair.
- When debugging, you can clear the framework cache (`php artisan cache:clear`) to reset all cached values.

## Return values and defaults

- If a value or nested path does not exist, the provided default is returned.
- If the stored JSON value is `null`, the default is returned.
- `getGroup()` returns an associative array of all settings for the group, or an empty array if none exist.

## Database engines

This package stores settings as JSON. Ensure your chosen database supports JSON columns. For SQLite (common in tests), JSON is stored as text and works transparently for typical use cases.

## Security considerations

> ⚠️ DB Config is a place for values you want admins to edit safely at runtime, not for infrastructure secrets (API keys, DB credentials).
- Values are not encrypted by default. If you need encryption, apply it before using the package’s helpers to read or write values.


## Versioning

This package follows semantic versioning. Use a version constraint compatible with your Laravel version as shown in the installation section.

## License

The MIT License (MIT). See the LICENSE file for more details.
