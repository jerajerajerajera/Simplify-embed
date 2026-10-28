<?php

declare(strict_types=1);

namespace Simplify\Embed\Services;

/**
 * The code to paste into another website.
 */
final class Snippet
{
    /** A server's short id. */
    public const ID = '/^[A-Za-z0-9]{1,16}$/';

    /** A list of short ids, comma separated. */
    public const IDS = '/^[A-Za-z0-9]{1,16}(,[A-Za-z0-9]{1,16}){0,99}$/';

    public const GAME = '/^[\pL\pN .:+\'()&_-]{1,60}$/u';

    /**
     * $show limits the list to one server (['server' => short id]), one game (['game' => name]) or a set
     * (['servers' => [short ids]]); empty shows every server on the website. 'page' is the same list as a page of
     * its own, for example a status page for one server.
     *
     * @param  array<string, mixed>  $show
     * @return array{script: string, iframe: string, page: string}
     */
    public static function make(string $origin, array $show = []): array
    {
        $origin = rtrim($origin, '/');
        $query = [];
        if (is_string($show['server'] ?? null) && preg_match(self::ID, $show['server'])) {
            $query['server'] = $show['server'];
        } elseif (is_string($show['game'] ?? null) && preg_match(self::GAME, $show['game'])) {
            $query['game'] = $show['game'];
        } elseif (is_array($show['servers'] ?? null)) {
            $ids = array_values(array_filter(array_map('strval', $show['servers']), fn (string $id) => preg_match(self::ID, $id) === 1));
            if ($ids !== []) {
                $query['servers'] = implode(',', array_slice($ids, 0, ServerList::LIMIT));
            }
        }
        $attributes = '';
        foreach ($query as $key => $value) {
            $attributes .= ' data-'.$key.'="'.htmlspecialchars($value, ENT_QUOTES).'"';
        }
        $string = $query === [] ? '' : '?'.http_build_query($query, '', '&amp;', PHP_QUERY_RFC3986);
        $height = isset($query['server']) ? 190 : 420;

        return [
            'script' => '<div id="simplify-servers"></div>'."\n".'<script src="'.$origin.'/simplify/embed.js"'.$attributes.' defer></script>',
            'iframe' => '<iframe src="'.$origin.'/simplify/embed'.$string.'" title="Game servers" style="width:100%;height:'.$height.'px;border:0" loading="lazy"></iframe>',
            'page' => $origin.'/simplify/embed?'.http_build_query([...$query, 'page' => 1], '', '&', PHP_QUERY_RFC3986),
        ];
    }
}
