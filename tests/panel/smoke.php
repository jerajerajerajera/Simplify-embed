<?php

// Loads Simplify Embed inside a real Pelican panel, opens its pages and reads the public list. Run from the panel
// folder after `php artisan p:plugin:install simplify-embed`: php plugins/simplify-embed/tests/panel/smoke.php
use App\Models\Allocation;
use App\Models\Egg;
use App\Models\Node;
use App\Models\Role;
use App\Models\Server;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\InteractsWithSession;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Testing\TestResponse;
use Simplify\Embed\Filament\Admin\Pages\EmbedSettings;
use Simplify\Embed\Filament\Server\Pages\Website;
use Simplify\Embed\Services\EmbedServers;

define('LARAVEL_START', microtime(true));
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Laravel's console handler would print an uncaught error and still exit 0, which CI reads as a pass.
set_exception_handler(function (Throwable $e) {
    echo 'FAIL uncaught '.get_class($e).': '.substr($e->getMessage(), 0, 400).' ('.basename($e->getFile()).':'.$e->getLine().")\n";
    exit(1);
});

$failed = false;
function step(string $what, callable $run): void
{
    global $failed;
    try {
        $run();
        echo "PASS $what\n";
    } catch (Throwable $e) {
        $failed = true;
        echo "FAIL $what: ".get_class($e).': '.substr($e->getMessage(), 0, 400).' ('.basename($e->getFile()).':'.$e->getLine().")\n";
    }
}
function visit(?User $user, string $url): TestResponse
{
    $client = new class(app())
    {
        use InteractsWithAuthentication, InteractsWithSession, MakesHttpRequests;

        public function __construct(public $app) {}
    };

    return $user ? $client->actingAs($user)->get($url) : $client->get($url);
}
function mustBeOk(TestResponse $response, string $url): void
{
    if ($response->getStatusCode() !== 200) {
        throw new RuntimeException('HTTP '.$response->getStatusCode().' at '.$url.($response->exception ? ': '.$response->exception->getMessage() : ''));
    }
}
function must(bool $ok, string $why): void
{
    if (! $ok) {
        throw new RuntimeException($why);
    }
}

$admin = User::where('email', 'smoke-embed@example.com')->first()
    ?? User::factory()->create(['email' => 'smoke-embed@example.com']);
$admin->syncRoles(Role::getRootAdmin());
$owner = User::factory()->create();
$node = Node::factory()->create();
$allocation = Allocation::factory()->create(['node_id' => $node->id, 'ip' => '203.0.113.7', 'port' => 25565]);
$server = Server::factory()->create([
    'owner_id' => $owner->id,
    'node_id' => $node->id,
    'allocation_id' => $allocation->id,
    'egg_id' => Egg::factory()->create(['name' => 'Smoke Game'])->id,
    'name' => 'Smoke Oak',
]);
$allocation->update(['server_id' => $server->id]);

step('settings page renders', function () use ($admin) {
    $url = EmbedSettings::getUrl(panel: 'admin');
    $response = visit($admin, $url);
    mustBeOk($response, $url);
    must(str_contains($response->getContent(), 'simplify/embed.js'), 'the code for a website is missing');
});
step('the public list starts empty and allows any website', function () {
    $response = visit(null, '/simplify/embed/servers.json');
    mustBeOk($response, 'servers.json');
    must($response->headers->get('Access-Control-Allow-Origin') === '*', 'no CORS header');
    must($response->json('servers') === [], 'the list is not empty');
    must(is_string($response->json('texts.online')), 'the texts are missing');
});
step('a server put on the website shows with its public address only', function () use ($server) {
    EmbedServers::setVisible($server, true);
    $servers = visit(null, '/simplify/embed/servers.json')->json('servers');
    must(count($servers) === 1, 'expected one server, got '.count($servers));
    must($servers[0]['name'] === 'Smoke Oak' && $servers[0]['game'] === 'Smoke Game', 'wrong name or game');
    must($servers[0]['address'] === '203.0.113.7:25565', 'wrong address: '.var_export($servers[0]['address'], true));
    foreach (['uuid', 'owner_id', 'node_id', 'node'] as $private) {
        must(! array_key_exists($private, $servers[0]), "$private is published");
    }
});
step('the script and the iframe page load', function () {
    $script = visit(null, '/simplify/embed.js');
    mustBeOk($script, 'embed.js');
    must(str_contains((string) $script->headers->get('Content-Type'), 'javascript'), 'embed.js is not JavaScript');
    $page = visit(null, '/simplify/embed?page=1&style=dark&server=abc');
    mustBeOk($page, 'embed page');
    must(str_contains($page->getContent(), 'data-server="abc"'), 'the option did not reach the script');
});
step('the owner opens Website on the server', function () use ($owner, $server) {
    $server->update(['status' => null]);
    $url = Website::getUrl(panel: 'server', tenant: $server);
    mustBeOk(visit($owner, $url), $url);
});
step('another user cannot open Website on the server', function () use ($server) {
    $url = Website::getUrl(panel: 'server', tenant: $server);
    must(visit(User::factory()->create(), $url)->getStatusCode() !== 200, 'a stranger could open the page');
});
step('deleting the server takes it off the website', function () use ($server) {
    $server->delete();
    must(EmbedServers::ids() === [], 'the server is still listed');
});
if ($failed) {
    exit(1);
}
echo "All steps passed\n";
