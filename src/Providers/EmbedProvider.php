<?php

declare(strict_types=1);

namespace Simplify\Embed\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Simplify\Embed\Http\Controllers\EmbedController;
use Simplify\Embed\Simplify\EmbedAddon;

final class EmbedProvider extends ServiceProvider
{
    public function register(): void
    {
        // Shows the add-on inside Simplify when Simplify is installed. Tagging stores only the class name.
        $this->app->tag([EmbedAddon::class], 'simplify.addons');
    }

    public function boot(): void
    {
        // Public and read-only, without a session, so any website can show the list. These are the addresses
        // PelicanSimplify used, so websites that already show the list keep working.
        Route::get('/simplify/embed/servers.json', [EmbedController::class, 'servers'])
            ->middleware('throttle:120,1')
            ->name('simplify-embed.servers');
        Route::get('/simplify/embed.js', [EmbedController::class, 'script'])
            ->name('simplify-embed.script');
        Route::get('/simplify/embed', [EmbedController::class, 'frame'])
            ->middleware('throttle:120,1')
            ->name('simplify-embed.frame');
    }
}
