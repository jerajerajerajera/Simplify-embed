<?php

declare(strict_types=1);

namespace Simplify\Embed\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Simplify\Embed\Services\ServerList;
use Simplify\Embed\Services\Snippet;
use Simplify\Embed\SimplifyEmbedPlugin;

/**
 * For other websites: the server list as JSON, the script that draws it and a small page for an iframe.
 * Public and read-only, so every response allows any origin and nothing depends on a session.
 */
final class EmbedController
{
    private const HEADERS = ['Access-Control-Allow-Origin' => '*', 'X-Content-Type-Options' => 'nosniff'];

    public function servers(): Response
    {
        $json = json_encode(ServerList::payload(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return response($json, 200, [
            ...self::HEADERS,
            'Content-Type' => 'application/json',
            'Cache-Control' => 'public, max-age='.ServerList::TTL,
        ]);
    }

    public function script(Request $request): Response
    {
        $path = plugin_path(SimplifyEmbedPlugin::ID, 'resources', 'js', 'embed.js');
        $etag = '"'.substr(hash_file('sha256', $path), 0, 16).'"';
        $headers = [
            ...self::HEADERS,
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=300',
            'ETag' => $etag,
        ];
        if (trim((string) $request->header('If-None-Match')) === $etag) {
            return response('', 304, $headers);
        }

        return response((string) file_get_contents($path), 200, $headers);
    }

    /**
     * A page that only holds the list, for websites that prefer an iframe. The query takes the same options as the
     * script's data-* attributes. With ?page=1 it is a page of its own, with some room and a background.
     */
    public function frame(Request $request): Response
    {
        $rules = ['server' => Snippet::ID, 'game' => Snippet::GAME, 'servers' => Snippet::IDS];
        $attributes = '';
        foreach (['style', 'accent', 'corners', 'players', 'modpack', 'join', 'server', 'game', 'servers'] as $key) {
            $value = $request->query($key);
            if (is_string($value) && preg_match($rules[$key] ?? '/^[#A-Za-z0-9_-]{1,40}$/', $value)) {
                $attributes .= ' data-'.$key.'="'.e($value).'"';
            }
        }
        $dark = 'html{background:#0b0b0e}';
        $style = match (true) {
            $request->query('page') !== '1' => 'html,body{background:transparent}body{padding:2px}',
            $request->query('style') === 'dark' => $dark,
            $request->query('style') === 'light' => 'html{background:#fafafa}',
            default => 'html{background:#fafafa}@media (prefers-color-scheme:dark){'.$dark.'}',
        };
        if ($request->query('page') === '1') {
            $style .= 'body{padding:24px 16px;max-width:1100px;margin:0 auto}';
        }
        $html = '<!doctype html><html lang="'.e(str_replace('_', '-', app()->getLocale())).'"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>'.e(__('simplify-embed::list.title', ['name' => (string) config('app.name', 'Pelican')])).'</title>'
            .'<style>html,body{margin:0}'.$style.'</style></head>'
            .'<body><div id="simplify-servers"></div><script src="'.e(url('/simplify/embed.js')).'"'.$attributes.' defer></script></body></html>';

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'public, max-age=300',
            'Content-Security-Policy' => 'frame-ancestors *',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
