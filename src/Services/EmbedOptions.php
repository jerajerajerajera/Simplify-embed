<?php

declare(strict_types=1);

namespace Simplify\Embed\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * How the list looks on other websites. Websites can still change each option with data-* attributes or the query.
 */
final class EmbedOptions
{
    public const TABLE = 'simplify_embed_settings';

    /** The Simplify blue, used when no colour is chosen. */
    public const ACCENT = '#5b8fd0';

    public const DEFAULTS = [
        'style' => 'auto',
        'corners' => 'round',
        'accent' => '',
        'players' => true,
        'modpack' => true,
        'join' => true,
    ];

    /** @return array{style: string, corners: string, accent: string, players: bool, modpack: bool, join: bool} */
    public static function all(): array
    {
        $saved = [];
        foreach (DB::table(self::TABLE)->get() as $row) {
            $saved[$row->key] = json_decode((string) $row->value, true);
        }

        return self::normalize($saved);
    }

    /** @param  array<string, mixed>  $input */
    public static function save(array $input): void
    {
        foreach (self::normalize($input) as $key => $value) {
            DB::table(self::TABLE)->updateOrInsert(['key' => $key], ['value' => json_encode($value)]);
        }
        Cache::forget(ServerList::CACHE);
    }

    /**
     * Only known options with valid values, anything else falls back to its default.
     *
     * @param  array<string, mixed>  $input
     * @return array{style: string, corners: string, accent: string, players: bool, modpack: bool, join: bool}
     */
    public static function normalize(array $input): array
    {
        $out = self::DEFAULTS;
        if (in_array($input['style'] ?? null, ['auto', 'light', 'dark'], true)) {
            $out['style'] = $input['style'];
        }
        if (in_array($input['corners'] ?? null, ['round', 'square'], true)) {
            $out['corners'] = $input['corners'];
        }
        if (is_string($input['accent'] ?? null) && preg_match('/^#[0-9a-fA-F]{6}$/', $input['accent'])) {
            $out['accent'] = strtolower($input['accent']);
        }
        foreach (['players', 'modpack', 'join'] as $key) {
            if (array_key_exists($key, $input)) {
                $out[$key] = (bool) $input[$key];
            }
        }

        return $out;
    }
}
