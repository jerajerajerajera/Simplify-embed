<?php

declare(strict_types=1);

namespace Simplify\Embed;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Simplify\Embed\Filament\Admin\Pages\EmbedSettings;
use Simplify\Embed\Filament\Server\Pages\Website;

final class SimplifyEmbedPlugin implements Plugin
{
    public const ID = 'simplify-embed';

    public function getId(): string
    {
        return self::ID;
    }

    public function register(Panel $panel): void
    {
        if ($panel->getId() === 'admin') {
            $panel->pages([EmbedSettings::class]);
        }
        if ($panel->getId() === 'server') {
            $panel->pages([Website::class]);
        }
    }

    public function boot(Panel $panel): void {}
}
