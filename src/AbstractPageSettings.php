<?php

namespace Inerba\DbConfig;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;

/**
 * @property object|null $content Instance of the content (form/schema) used by the page.
 */
abstract class AbstractPageSettings extends Page
{
    use InteractsWithSchemas;

    /**
     * Data loaded from the DB config group.
     *
     * @var array<string,mixed>|null
     */
    public ?array $data = [];

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    public static function getNavigationGroup(): ?string
    {
        return __('db-config::db-config.navigation_group');
    }

    abstract protected function settingName(): string;

    /**
     * Provide default values.
     *
     * These defaults will be used when initializing the page and may be merged with any
     * persisted or user-provided overrides.
     *
     * @return array<string,mixed>
     */
    public function getDefaultData(): array
    {
        return [];
    }

    public function mount(): void
    {
        $db = DbConfig::getGroup($this->settingName()) ?? [];
        $defaults = $this->getDefaultData();

        // Merge defaults with DB values: DB values take precedence.
        $this->data = array_replace_recursive($defaults, $db);

        $this->content->fill($this->data);
    }

    public function save(): void
    {
        /** @var array<string,mixed> $state */
        $state = $this->content->getState();

        collect($state)->each(function ($setting, $key) {
            DbConfig::set($this->settingName() . '.' . $key, $setting);
        });

        Notification::make()
            ->success()
            ->title(__('db-config::db-config.saved_title'))
            ->body(__('db-config::db-config.saved_body'))
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label(__('db-config::db-config.save'))
                ->action(fn () => $this->save()),
        ];
    }
}
