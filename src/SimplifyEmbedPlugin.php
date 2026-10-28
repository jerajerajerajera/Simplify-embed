<?php

declare(strict_types=1);

namespace Simplify\Embed;

use Filament\Contracts\Plugin;
use Filament\Panel;

final class SimplifyEmbedPlugin implements Plugin
{
    public const ID = 'simplify-embed';

    public function getId(): string
    {
        return self::ID;
    }

    public function register(Panel $panel): void {}

    public function boot(Panel $panel): void {}
}
