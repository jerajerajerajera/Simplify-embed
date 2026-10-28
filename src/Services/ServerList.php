<?php

declare(strict_types=1);

namespace Simplify\Embed\Services;

use App\Enums\ContainerStatus;
use App\Models\Server;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Simplify\Subdomains\Subdomains;
use Throwable;

/**
 * The public list: only what a visitor needs (name, game, status, players and a public join address).
 * Never ids, owners, nodes or private addresses.
 *
 * Other plugins can add details through the "simplify.embed.server" event. A listener receives the Server and
 * returns an array with any of: game, software, version, players, max_players, motd, address, icon and
 * modpack (name, version, url, icon). Simplify Mods adds players and modpacks this way. The address of
 * Simplify Subdomains is used when that plugin is installed.
 */
final class ServerList
{
    public const CACHE = 'simplify-embed.servers';

    public const EVENT = 'simplify.embed.server';

    /** Seconds the list is cached, so visitors never reach Wings or a game server. */
    public const TTL = 60;

    /** At most this many servers are published, so one request never walks a whole host. */
    public const LIMIT = 100;

    /** @return array{options: array<string, mixed>, servers: list<array<string, mixed>>, name: string, texts: array<string, string>} */
    public static function payload(): array
    {
        $options = EmbedOptions::all();
        if ($options['accent'] === '') {
            $options['accent'] = EmbedOptions::ACCENT;
        }

        return [
            'options' => $options,
            'servers' => Cache::remember(self::CACHE, self::TTL, fn (): array => self::collect()),
            'name' => (string) config('app.name', 'Pelican'),
            'texts' => self::texts(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function collect(): array
    {
        $servers = Server::query()
            ->whereIn('id', EmbedServers::ids())
            ->with(['allocation', 'egg', 'node'])
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get();
        $out = [];
        foreach ($servers as $server) {
            try {
                $out[] = self::entry($server);
            } catch (Throwable $error) {
                report($error);
            }
        }

        return $out;
    }

    /** @return array<string, mixed> */
    public static function entry(Server $server): array
    {
        $state = self::state($server);
        $extra = self::extra($server, $state === 'online');
        $allocation = $server->allocation;
        $port = (int) ($allocation?->port ?? 0);

        return self::clean([
            'id' => (string) $server->uuid_short,
            'name' => (string) $server->name,
            'game' => (string) ($extra['game'] ?? $server->egg?->name ?? 'Game'),
            'software' => $extra['software'] ?? null,
            'version' => $extra['version'] ?? null,
            'state' => $state,
            'players' => $state === 'online' ? ($extra['players'] ?? null) : null,
            'max_players' => $state === 'online' ? ($extra['max_players'] ?? null) : null,
            'motd' => $extra['motd'] ?? null,
            'address' => PublicAddress::first([
                is_string($extra['address'] ?? null) ? $extra['address'] : null,
                self::subdomain($server),
                PublicAddress::join($allocation?->ip_alias, $port),
                PublicAddress::join($allocation?->ip, $port),
            ]),
            'icon' => $extra['icon'] ?? $extra['modpack']['icon'] ?? $server->egg?->icon,
            'modpack' => $extra['modpack'] ?? null,
        ]);
    }

    /** The address from Simplify Subdomains when it is installed and the subdomain works. */
    private static function subdomain(Server $server): ?string
    {
        if (! class_exists(Subdomains::class)) {
            return null;
        }
        try {
            return Subdomains::address($server);
        } catch (Throwable $error) {
            report($error);

            return null;
        }
    }

    /** online, starting, stopping, installing or offline. */
    public static function state(Server $server): string
    {
        if ($server->status !== null) {
            return str_contains((string) $server->status->value, 'install') ? 'installing' : 'offline';
        }

        try {
            $status = $server->retrieveStatus();
        } catch (Throwable) {
            // Wings cannot be reached: visitors see the server as offline instead of losing the whole list.
            return 'offline';
        }

        return match ($status) {
            ContainerStatus::Running => 'online',
            ContainerStatus::Starting, ContainerStatus::Restarting => 'starting',
            ContainerStatus::Stopping => 'stopping',
            default => 'offline',
        };
    }

    /**
     * What other plugins add for this server. $running tells listeners whether asking the game server makes sense.
     *
     * @return array<string, mixed>
     */
    private static function extra(Server $server, bool $running): array
    {
        if (! Event::hasListeners(self::EVENT)) {
            return [];
        }
        $extra = [];
        foreach (Event::dispatch(self::EVENT, [$server, $running]) ?? [] as $result) {
            if (is_array($result)) {
                $extra = [...$extra, ...$result];
            }
        }

        return $extra;
    }

    /**
     * Cuts every text to a sane length and drops links that are not https, whatever a listener returned.
     *
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    public static function clean(array $entry): array
    {
        $text = fn ($value, int $length): ?string => is_scalar($value) && trim((string) $value) !== ''
            ? mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]/u', ' ', (string) $value) ?? ''), 0, $length)
            : null;
        $count = fn ($value): ?int => is_numeric($value) && (int) $value >= 0 ? min((int) $value, 1000000) : null;
        $pack = is_array($entry['modpack'] ?? null) && $text($entry['modpack']['name'] ?? null, 80) !== null ? [
            'name' => $text($entry['modpack']['name'], 80),
            'version' => $text($entry['modpack']['version'] ?? null, 40),
            'url' => self::safeUrl($entry['modpack']['url'] ?? null),
            'icon' => self::safeUrl($entry['modpack']['icon'] ?? null),
        ] : null;

        return [
            'id' => (string) $entry['id'],
            'name' => $text($entry['name'], 80) ?? '',
            'game' => $text($entry['game'], 60) ?? 'Game',
            'software' => $text($entry['software'] ?? null, 60),
            'version' => $text($entry['version'] ?? null, 40),
            'state' => in_array($entry['state'], ['online', 'starting', 'stopping', 'installing'], true) ? $entry['state'] : 'offline',
            'players' => $count($entry['players'] ?? null),
            'max_players' => $count($entry['max_players'] ?? null),
            'motd' => $text($entry['motd'] ?? null, 200),
            'address' => $entry['address'] ?? null,
            'icon' => self::safeUrl($entry['icon'] ?? null),
            'modpack' => $pack,
        ];
    }

    /** An https link, or null. */
    public static function safeUrl(mixed $url): ?string
    {
        if (! is_string($url) || strlen($url) > 500 || ! str_starts_with($url, 'https://')) {
            return null;
        }

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    /**
     * The games of the servers on the website, for "only this game" in the code.
     *
     * @return list<string>
     */
    public static function games(): array
    {
        $games = array_unique(array_column(Cache::remember(self::CACHE, self::TTL, fn (): array => self::collect()), 'game'));
        sort($games);

        return array_values($games);
    }

    /**
     * The texts embed.js shows, in the panel's language.
     *
     * @return array<string, string>
     */
    private static function texts(): array
    {
        $texts = [];
        foreach (['online', 'starting', 'stopping', 'installing', 'offline', 'players', 'copy', 'copied', 'selected', 'view', 'empty', 'copy_address'] as $key) {
            $texts[$key] = __('simplify-embed::list.'.$key);
        }

        return $texts;
    }
}
