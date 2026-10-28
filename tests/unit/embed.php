<?php

// The parts of the embed that need no panel: options, public addresses, the code for a website and cleaning
// what other plugins add. Run: php tests/unit/embed.php
declare(strict_types=1);

foreach (['EmbedOptions', 'PublicAddress', 'ServerList', 'Snippet'] as $class) {
    require __DIR__.'/../../src/Services/'.$class.'.php';
}

use Simplify\Embed\Services\EmbedOptions;
use Simplify\Embed\Services\PublicAddress;
use Simplify\Embed\Services\ServerList;
use Simplify\Embed\Services\Snippet;

$failures = [];
$checks = 0;
function same(string $what, mixed $want, mixed $have): void
{
    global $failures, $checks;
    $checks++;
    if ($want !== $have) {
        $failures[] = $what.': wanted '.var_export($want, true).', got '.var_export($have, true);
    }
}

// Options: unknown keys and bad values fall back to the defaults.
same('defaults', EmbedOptions::DEFAULTS, EmbedOptions::normalize([]));
same('valid options', ['style' => 'dark', 'corners' => 'square', 'accent' => '#abcdef', 'players' => false, 'modpack' => true, 'join' => false],
    EmbedOptions::normalize(['style' => 'dark', 'corners' => 'square', 'accent' => '#ABCDEF', 'players' => 0, 'join' => '', 'evil' => 'x']));
same('bad style', 'auto', EmbedOptions::normalize(['style' => 'neon'])['style']);
same('bad accent', '', EmbedOptions::normalize(['accent' => 'red;background:url(x)'])['accent']);

// Public addresses only.
foreach (['play.example.com:25565', '203.0.113.9:25565', '8.8.8.8:27015', '[2001:4860::1]:25565'] as $address) {
    same("reachable $address", true, PublicAddress::reachable($address));
}
foreach (['192.168.1.10:25565', '10.0.0.2:1', '127.0.0.1:25565', '100.101.102.103:25565', '0.0.0.0:25565', 'box.tail1234.ts.net:25565',
    'server.local:25565', 'localhost:25565', '[fd7a:115c:a1e0::1]:25565', '[::1]:25565', 'nodots:25565'] as $address) {
    same("unreachable $address", false, PublicAddress::reachable($address));
}
same('join ipv4', '203.0.113.9:25565', PublicAddress::join('203.0.113.9', 25565));
same('join ipv6', '[2001:db8::1]:25565', PublicAddress::join('2001:db8::1', 25565));
same('join without port', null, PublicAddress::join('203.0.113.9', 0));
same('first reachable', 'play.example.com:25566', PublicAddress::first([null, '192.168.1.2:25566', 'play.example.com:25566']));
same('none reachable', null, PublicAddress::first(['10.0.0.1:1', '']));

// The code for a website.
$all = Snippet::make('https://panel.example.com/');
same('script', '<div id="simplify-servers"></div>'."\n".'<script src="https://panel.example.com/simplify/embed.js" defer></script>', $all['script']);
same('page', 'https://panel.example.com/simplify/embed?page=1', $all['page']);
$one = Snippet::make('https://panel.example.com', ['server' => '1a2b3c4d']);
same('one server attribute', true, str_contains($one['script'], ' data-server="1a2b3c4d"'));
same('one server iframe', true, str_contains($one['iframe'], '/simplify/embed?server=1a2b3c4d" ') && str_contains($one['iframe'], 'height:190px'));
same('one server page', 'https://panel.example.com/simplify/embed?server=1a2b3c4d&page=1', $one['page']);
$game = Snippet::make('https://panel.example.com', ['game' => "Don't Starve"]);
same('game escaped', true, str_contains($game['script'], 'data-game="Don&#039;t Starve"'));
same('bad server ignored', Snippet::make('https://p.example')['script'], Snippet::make('https://p.example', ['server' => '"><script>'])['script']);
same('servers list', true, str_contains(Snippet::make('https://p.example', ['servers' => ['ab', 'c d', 'ef']])['script'], 'data-servers="ab,ef"'));

// What other plugins add is cut to size and only https links survive.
$clean = ServerList::clean([
    'id' => 'abc', 'name' => str_repeat('n', 200), 'game' => "Mine\x07craft", 'state' => 'weird', 'players' => '7', 'max_players' => -1,
    'icon' => 'javascript:alert(1)', 'address' => 'play.example.com:25565',
    'modpack' => ['name' => 'All the Mods', 'version' => '1.2', 'url' => 'http://example.com', 'icon' => 'https://cdn.example.com/a.png'],
]);
same('name cut', 80, mb_strlen($clean['name']));
same('control characters', 'Mine craft', $clean['game']);
same('unknown state', 'offline', $clean['state']);
same('players', 7, $clean['players']);
same('negative max', null, $clean['max_players']);
same('javascript icon', null, $clean['icon']);
same('http modpack link', null, $clean['modpack']['url']);
same('https modpack icon', 'https://cdn.example.com/a.png', $clean['modpack']['icon']);
same('no modpack without a name', null, ServerList::clean(['id' => 'a', 'name' => 'a', 'game' => 'a', 'state' => 'online', 'modpack' => ['url' => 'https://x.example']])['modpack']);

if ($failures) {
    echo "FAIL embed:\n  ".implode("\n  ", $failures)."\n";
    exit(1);
}
echo "PASS embed: $checks checks\n";
