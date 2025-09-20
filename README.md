# Filament DB Config Encrypt

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ngankt2/filament-db-config-encrypt.svg?style=flat-square)](https://packagist.org/packages/ngankt2/filament-db-config-encrypt)
[![Total Downloads](https://img.shields.io/packagist/dt/ngankt2/filament-db-config-encrypt.svg?style=flat-square)](https://packagist.org/packages/ngankt2/filament-db-config-encrypt)
[![License](https://img.shields.io/packagist/l/ngankt2/filament-db-config-encrypt.svg?style=flat-square)](https://github.com/ngankt2/filament-db-config-security/blob/main/LICENSE)

A Filament plugin for managing database-backed application settings and editable content with caching and encryption support. It provides an Artisan command to generate settings pages and a helper function to retrieve settings in Blade templates or PHP code.

This project is a fork of [https://github.com/inerba/filament-db-config](https://github.com/inerba/filament-db-config). I only retrieved the source code to customize and use it privately, including renaming namespaces and adding several scenarios for encrypting data before storage.

Special thanks to the original author for their great work.

## Features
- **Database-Backed Settings**: Store and manage application settings in a database table.
- **Encryption Support**: Optionally encrypt settings for enhanced security.
- **Caching**: Cache settings with configurable TTL for performance optimization.
- **Filament Integration**: Generate Filament settings pages with a single Artisan command.
- **Helper Function**: Easily access settings in Blade templates or PHP code using the `db_config()` helper.
- **Flexible Grouping**: Organize settings into groups for better management.
- **Customizable Views**: Support for custom Blade views or a default view for settings pages.

## Requirements
- PHP: ^8.2
- Laravel: ^10.0 or ^11.0 or ^12.0
- Filament: ^3.0 or ^4.0
- ngankt2/laravel-helper: ^1.0

## Installation
Install the package via Composer:

```bash
composer require ngankt2/filament-db-config-encrypt
```

After installation, publish the configuration file and run the migrations:

```bash
php artisan vendor:publish --provider="Ngankt2\DbConfig\DbConfigServiceProvider"
php artisan migrate
```

This will create a `db_config` table in your database to store settings.

## Configuration
The configuration file is located at `config/db-config.php`. Below is an example configuration:

```php
return [
    'table_name' => 'db_config', // The database table to store settings
    'encrypt' => true, // Enable encryption for stored settings
    'cache' => [
        'prefix' => 'db-config', // Cache key prefix
        'ttl' => 60, // Cache TTL in minutes (0 for no expiration)
    ],
];
```
```dotenv
# .env example for encryption keys
ENCRYPT_DB_OTHER=YOUR_KEY_HERE
```

- `table_name`: The name of the database table to store settings.
- `encrypt`: Set to `true` to encrypt settings before storing them in the database.
- `cache.prefix`: The prefix for cache keys.
- `cache.ttl`: Time-to-live for cached settings in minutes. Set to `0` to cache indefinitely.

## Usage

### Generating a Settings Page
Use the provided Artisan command to generate a Filament settings page:

```bash
php artisan make:db-config
```

This command will:
- Create a settings page class (e.g., `app/Filament/Admin/Clusters/SettingsCluster/Pages/GeneralSettings.php`).
- Optionally create a Blade view if you choose to use a custom view.
- Support clusters for organizing settings pages.

The generated page extends `Ngankt2\DbConfig\AbstractPageSettings` and includes methods to define the settings group, default data, and form schema.

### Defining Settings
Edit the generated settings page to define the form schema and default data. For example:

```php
<?php

namespace App\Filament\Admin\Clusters\SettingsCluster\Pages;

use Ngankt2\DbConfig\AbstractPageSettings;
use Filament\Forms\Components;

class GeneralSettings extends AbstractPageSettings
{
    protected static ?string $title = 'General Settings';
    protected static ?string $cluster = \App\Filament\Admin\Clusters\SettingsCluster::class;

    protected function settingName(): string
    {
        return 'general';
    }

    public function getDefaultData(): array
    {
        return [
            'site_name' => 'My Application',
            'site_description' => 'A description of my application',
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Components\TextInput::make('site_name')
                    ->label('Site Name')
                    ->required(),
                Components\Textarea::make('site_description')
                    ->label('Site Description'),
            ])
            ->statePath('data');
    }
}
```

### Accessing Settings
Use the `db_config()` helper function to retrieve settings in Blade templates or PHP code:

```php
// In PHP code
$value = db_config('general', [
            'site_name' => 'My Application',
            'site_description' => 'A description of my application',
        ]);
```

The helper retrieves settings from the `db_config` table, using the cache if configured. If encryption is enabled, the settings are automatically decrypted.

### Saving Settings
The generated settings page includes a "Save" action that persists form data to the database. When the form is submitted, the `save()` method in `AbstractPageSettings` stores the data under the specified group and key, with optional merging of existing values.

### Customizing the View
When generating a settings page, you can choose to create a custom Blade view. If you opt out, the page uses the default view (`db-config::settings-base`). To customize the view, specify a custom view path during generation:

```bash
php artisan make:db-config
```

Then, edit the generated Blade view (e.g., `resources/views/db-config/[panel-id].general-settings.blade.php`) to customize the layout.

## Database Structure
The `db_config` table has the following structure:

| Column       | Type      | Description                                                 |
|--------------|-----------|-------------------------------------------------------------|
| `group`      | string    | The settings group (e.g., 'default' or tenant ID).               |
| `key`        | string    | The settings key (e.g., 'general').                         |
| `settings`   | text      | The JSON-encoded (and optionally encrypted) settings value. |
| `created_at` | timestamp | Creation timestamp.                                         |
| `updated_at` | timestamp | Last update timestamp.                                      |

## Advanced Usage

### Bypassing Cache
To retrieve settings without using the cache:

```php
$value = \Ngankt2\DbConfig\DbConfig::getWithoutCache('general', [
            'site_name' => 'My Application',
            'site_description' => 'A description of my application',
        ]);
```

### Merging Settings
By default, settings are merged with existing values. To override instead of merging, override the `getMerge()` method in your settings page:

```php
protected function getMerge(): bool
{
    return false; // Override existing values instead of merging
}
```

### Custom Group Name
To use a different group name for settings, override the `groupName()` method:

```php
protected function groupName(): string
{
    return 'custom-group';
}
```

## Contributing
Contributions are welcome! Please submit a pull request or open an issue on the [GitHub repository](https://github.com/ngankt2/filament-db-config-security).

## Support
If you encounter any issues or have questions, please open an issue on the [GitHub repository](https://github.com/ngankt2/filament-db-config-security) or contact the author at [codezi.pro@gmail.com](mailto:codezi.pro@gmail.com).

## License
This package is open-sourced software licensed under the [MIT license](https://github.com/ngankt2/filament-db-config-security/blob/main/LICENSE).
