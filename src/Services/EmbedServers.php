<?php

declare(strict_types=1);

namespace Simplify\Embed\Services;

use App\Models\Server;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Which servers are on the website. A server owner or a root admin chooses this; nothing is shown by default.
 */
final class EmbedServers
{
    public const TABLE = 'simplify_embed_servers';

    public static function visible(Server $server): bool
    {
        return DB::table(self::TABLE)->where('server_id', $server->id)->exists();
    }

    public static function setVisible(Server $server, bool $visible): void
    {
        if ($visible) {
            DB::table(self::TABLE)->updateOrInsert(['server_id' => $server->id], ['created_at' => now(), 'updated_at' => now()]);
        } else {
            DB::table(self::TABLE)->where('server_id', $server->id)->delete();
        }
        Cache::forget(ServerList::CACHE);
    }

    /** @return list<int> */
    public static function ids(): array
    {
        return DB::table(self::TABLE)->pluck('server_id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Shows exactly these servers.
     *
     * @param  list<int>  $ids
     */
    public static function replace(array $ids): void
    {
        $ids = Server::query()->whereIn('id', $ids)->pluck('id')->map(fn ($id) => (int) $id)->all();
        DB::transaction(function () use ($ids) {
            DB::table(self::TABLE)->whereNotIn('server_id', $ids)->delete();
            $known = self::ids();
            foreach (array_diff($ids, $known) as $id) {
                DB::table(self::TABLE)->insert(['server_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            }
        });
        Cache::forget(ServerList::CACHE);
    }

    /** True for the server's owner and for root admins. */
    public static function canManage(Server $server): bool
    {
        $user = user();

        return $user !== null && ($user->isRootAdmin() || (int) $server->owner_id === (int) $user->id);
    }
}
