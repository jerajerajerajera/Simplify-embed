<?php

declare(strict_types=1);

namespace Simplify\Embed\Filament\Admin\Pages;

use App\Models\Server;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Simplify\Embed\Services\EmbedOptions;
use Simplify\Embed\Services\EmbedServers;
use Simplify\Embed\Services\ServerList;

/**
 * Where a root admin picks the servers on the website, how the list looks, and copies the code for a website.
 *
 * @property Schema $form
 */
final class EmbedSettings extends Page implements HasSchemas
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-code';

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'simplify-embed';

    protected string $view = 'simplify-embed::form-page';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) user()?->isRootAdmin();
    }

    public static function getNavigationLabel(): string
    {
        return __('simplify-embed::embed.name');
    }

    /** In the Simplify group of Pelican's menu, with the other Simplify add-ons (Simplify's ADDONS.md, "Admin menu"). */
    public static function getNavigationGroup(): ?string
    {
        return 'Simplify';
    }

    public function getTitle(): string|Htmlable
    {
        return __('simplify-embed::embed.name');
    }

    public function mount(): void
    {
        $this->form->fill([
            'options' => EmbedOptions::all(),
            'servers' => EmbedServers::ids(),
            'code' => ['show' => 'all'],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $t = fn (string $key) => __('simplify-embed::embed.settings.'.$key);

        return $schema->statePath('data')->components([
            Section::make($t('servers'))->description($t('servers_help'))->schema([
                CheckboxList::make('servers')
                    ->hiddenLabel()
                    ->options(fn () => Server::query()->orderBy('name')->limit(500)->pluck('name', 'id')->all())
                    ->searchable()
                    ->bulkToggleable()
                    ->columns(2),
            ]),
            Section::make($t('look'))->description($t('look_help'))->columns(2)->schema([
                Select::make('options.style')->label($t('style'))->selectablePlaceholder(false)->required()->options([
                    'auto' => $t('style_auto'),
                    'light' => $t('style_light'),
                    'dark' => $t('style_dark'),
                ]),
                Select::make('options.corners')->label($t('corners'))->selectablePlaceholder(false)->required()->options([
                    'round' => $t('corners_round'),
                    'square' => $t('corners_square'),
                ]),
                ColorPicker::make('options.accent')
                    ->label($t('accent'))
                    ->regex('/^#[0-9a-fA-F]{6}$/')
                    ->placeholder(EmbedOptions::ACCENT)
                    ->helperText($t('accent_help')),
                Grid::make(1)->schema([
                    Toggle::make('options.players')->label($t('players')),
                    Toggle::make('options.modpack')->label($t('modpack')),
                    Toggle::make('options.join')->label($t('join'))->helperText($t('join_help')),
                ]),
            ]),
            Section::make($t('code'))->description($t('code_help'))->columns(2)->schema([
                Select::make('code.show')->label($t('show'))->live()->selectablePlaceholder(false)->options([
                    'all' => $t('show_all'),
                    'server' => $t('show_server'),
                    'game' => $t('show_game'),
                ]),
                Select::make('code.server')
                    ->label($t('server'))
                    ->live()
                    ->placeholder($t('pick_server'))
                    ->helperText($t('server_help'))
                    ->options(fn () => Server::query()->whereIn('id', EmbedServers::ids())->orderBy('name')->pluck('name', 'uuid_short')->all())
                    ->visible(fn (Get $get) => $get('code.show') === 'server'),
                Select::make('code.game')
                    ->label($t('game'))
                    ->live()
                    ->placeholder($t('pick_game'))
                    ->options(fn () => array_combine(ServerList::games(), ServerList::games()))
                    ->visible(fn (Get $get) => $get('code.show') === 'game'),
                View::make('simplify-embed::code')->columnSpanFull()->viewData(fn (Get $get) => $this->codeFor($get)),
            ]),
        ]);
    }

    /** @return array{show: array<string, string>|null} */
    private function codeFor(Get $get): array
    {
        return ['show' => match ($get('code.show')) {
            'server' => filled($get('code.server')) ? ['server' => (string) $get('code.server')] : null,
            'game' => filled($get('code.game')) ? ['game' => (string) $get('code.game')] : null,
            default => [],
        }];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->hiddenLabel()
                ->icon('tabler-device-floppy')
                ->tooltip(__('simplify-embed::embed.save'))
                ->action('save')
                ->keyBindings(['mod+s']),
        ];
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);
        $state = $this->form->getState();
        EmbedOptions::save((array) ($state['options'] ?? []));
        EmbedServers::replace(array_map('intval', (array) ($state['servers'] ?? [])));
        Notification::make()->title(__('simplify-embed::embed.saved'))->success()->send();
    }
}
