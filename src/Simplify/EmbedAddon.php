<?php

declare(strict_types=1);

namespace Simplify\Embed\Simplify;

use Simplify\Core\Addons\Addon;
use Simplify\Embed\Filament\Admin\Pages\EmbedSettings;
use Simplify\Embed\SimplifyEmbedPlugin;

/**
 * How the add-on shows inside Simplify. Simplify loads this class only when it is installed.
 */
final class EmbedAddon extends Addon
{
    public function id(): string
    {
        return SimplifyEmbedPlugin::ID;
    }

    public function name(): string
    {
        return __('simplify-embed::embed.name');
    }

    public function description(): string
    {
        return __('simplify-embed::embed.description');
    }

    public function icon(): string
    {
        return 'tabler-code';
    }

    public function settingsUrl(): ?string
    {
        return user()?->isRootAdmin() ? EmbedSettings::getUrl(panel: 'admin') : null;
    }
}
