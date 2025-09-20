<?php

declare(strict_types=1);

namespace Ngankt2\DbConfig;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use RuntimeException;

/**
 * Abstract base page for Filament settings pages that persist a named configuration group.
 *
 * Loads default values, merges them with persisted data from the database, and provides
 * lifecycle helpers and a save routine that persists form state into the corresponding
 * configuration group.
 *
 * @property object|null $form Instance of the content (form/schema) used by the page.
 * @property object|null $content Instance of the content (form/schema) used by the page.
 */
abstract class AbstractPageSettings extends Page
{
    /**
     * Data loaded from the DB config group.
     *
     * @var array<string,mixed>|null
     */
    public ?array $data = [];

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    /**
     * Returns the navigation group label used by the Filament UI to group this page.
     *
     * The value is retrieved from translation resources and may be null when a translation
     * is not defined.
     */
    public static function getNavigationGroup(): ?string
    {
        return __('db-config::db-config.navigation_group');
    }

    abstract protected function settingName(): string;

    protected function groupName(): string
    {
        return 'default';
    }
    protected function getMerge(): bool
    {
        return true;
    }

    /**
     * Returns the default data used to initialize the page state.
     *
     * These defaults are merged with persisted values; persisted values take precedence.
     *
     * @return array<string, mixed> Array of default values keyed by setting name.
     */
    public function getDefaultData(): array
    {
        return [];
    }

    /**
     * Initializes the page state by loading persisted values for the settings group and merging them with defaults.
     *
     * The merged result is assigned to the internal `$data` property.
     * If the page defines a `$form` or `$content` property, it is filled with the merged data.
     */
    public function mount(): void
    {
        $db = DbConfig::getWithoutCache($this->settingName(),[],  $this->groupName()) ?? [];
        $defaults = $this->getDefaultData();

        // Merge defaults with DB values: DB values take precedence.
        $this->data = array_replace_recursive($defaults, $db);

        // Support both $this->content and $this->form for the schema instance.
        if (!isset($this->form)) {
            $this->form = $this->content;
        }

        $this->form->fill($this->data);
    }

    /**
     * Persists the current form state into the associated settings group.
     *
     * If `$this->form` is not set, `$this->content` is used as fallback. The method verifies at runtime
     * that the form instance exposes `getState()`; it iterates every key/value pair returned by `getState()`
     * and calls `DbConfig::set("{settingName}.{key}", $value)` to persist each value. A Filament
     * notification is sent upon successful completion.
     *
     * @throws RuntimeException When the form instance is missing or does not provide `getState()`.
     */
    public function save(): void
    {
        // Support both $this->content and $this->form for the schema instance.
        if (!isset($this->form)) {
            $this->form = $this->content;
        }

        if (!is_object($this->form) || !method_exists($this->form, 'getState')) {
            throw new \RuntimeException('Expected $this->form to be an object exposing getState().');
        }

        /** @var array<string,mixed> $state */
        $state = $this->form->getState();
        DbConfig::set($this->settingName(), $state, $this->groupName() ?? 'default',  $this->getMerge());

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
                ->keyBindings(['mod+s'])
                ->action(fn() => $this->save()),
        ];
    }
}
