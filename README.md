# Simplify Embed

A [Pelican](https://pelican.dev) plugin that shows your game servers on any website: name, game, status, players and
the join address, with a copy button. It updates every minute by itself.

- **Script**: two lines for any website. The list grows with the number of servers and takes the website's font.
- **Iframe**: for website builders that do not allow scripts.
- **Page of its own**: a link to the same list, for example a status page for one server.

Only servers that their owner or a root admin put on the website are shown. Nothing else is published: no ids,
owners, nodes or private addresses (home network, Tailscale).

It works on its own. With [Simplify](https://github.com/jerajerajerajera/Simplify) installed it also shows in
Simplify's add-on list.

## Set up

1. Install the plugin from the [Pelican Hub](https://hub.pelican.dev).
2. In the admin area, open **Simplify → Website embed**. Tick the servers for your website and pick a style.
3. Copy the code under **Code for your website** and paste it into your website.

Server owners can also put their own server on the website under **Website** in the server's menu.

## Options in the code

On the script as `data-*` attributes, or in the iframe address as `?name=value`:

| Option | Values |
| --- | --- |
| `style` | `auto` (follows the visitor), `light`, `dark` |
| `accent` | a colour such as `#5b8fd0` |
| `corners` | `round`, `square` |
| `players`, `modpack`, `join` | `on`, `off` |
| `server` | a server's short id: only that server |
| `servers` | short ids, comma separated |
| `game` | only servers of this game |
| `target` | (script only) a CSS selector for where the list goes |

## Public addresses

The plugin adds three public, read-only addresses to your panel. They need no login and allow any website.

| Address | What it is |
| --- | --- |
| `/simplify/embed.js` | the script that draws the list |
| `/simplify/embed` | the list as a page, for an iframe or with `?page=1` as a page of its own |
| `/simplify/embed/servers.json` | the list itself, cached for a minute |

Visitors never reach Wings or a game server: the panel asks Wings at most once a minute. The plugin calls no outside
services.

## For other plugins

Another plugin can add details to a server in the list with the Laravel event `simplify.embed.server`. The listener
gets the `Server` and whether it is running, and returns any of: `game`, `software`, `version`, `players`,
`max_players`, `motd`, `address`, `icon` and `modpack` (`name`, `version`, `url`, `icon`). Links must be https.
Simplify Mods adds players and modpacks this way. With Simplify Subdomains installed, a server's subdomain is its
join address.

```php
Event::listen('simplify.embed.server', fn (Server $server, bool $running) => $running ? ['players' => 3, 'max_players' => 20] : []);
```

## Coming from PelicanSimplify

The addresses are the same as in PelicanSimplify, so websites that show the list keep working. Pick the servers for
the website once on the settings page.

## License

Source available, see [LICENSE.md](LICENSE.md). Copyright (c) 2026 Jeroen (jerajerajerajera).
