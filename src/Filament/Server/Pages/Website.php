<?php

declare(strict_types=1);

namespace Simplify\Embed\Filament\Server\Pages;

use App\Models\Server;
use Filament\Facades\Filament;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Simplify\Embed\Services\EmbedServers;

/**
 * The server's owner (or a root admin) puts the server on the website and copies the code for this one server.
 *
 * @property Schema $form
 */
final class Website extends Page implements HasSchemas
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-code';

    protected static ?int $navigationSort = 12;

    protected static ?string $slug = 'website';

    protected string $view = 'simplify-embed::form-page';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $server = Filament::getTenant();

        return $server instanceof Server && EmbedServers::canManage($server);
    }

    public static function getNavigationLabel(): string
    {
        return __('simplify-embed::embed.server.title');
    }

    public function getTitle(): string|Htmlable
    {
        return __('simplify-embed::embed.server.title');
    }

    public function server(): Server
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        return $server;
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);
        $this->form->fill(['visible' => EmbedServers::visible($this->server())]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make(__('simplify-embed::embed.server.title'))
                ->description(__('simplify-embed::embed.server.help'))
                ->schema([
                    Toggle::make('visible')
                        ->label(__('simplify-embed::embed.server.show'))
                        ->live()
                        ->afterStateUpdated(fn (bool $state) => $this->toggle($state)),
                    View::make('simplify-embed::code')
                        ->viewData(fn () => ['show' => ['server' => (string) $this->server()->uuid_short]])
                        ->visible(fn (Get $get) => (bool) $get('visible')),
                ]),
        ]);
    }

    public function toggle(bool $visible): void
    {
        abort_unless(self::canAccess(), 403);
        EmbedServers::setVisible($this->server(), $visible);
        Notification::make()
            ->title(__('simplify-embed::embed.server.'.($visible ? 'shown' : 'hidden')))
            ->success()
            ->send();
    }

    public function save(): void
    {
        $this->toggle((bool) ($this->form->getState()['visible'] ?? false));
    }
}
