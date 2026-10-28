<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // How the list looks, one row per option.
        Schema::create('simplify_embed_settings', function (Blueprint $table) {
            $table->string('key', 40)->primary();
            $table->json('value');
        });

        // The servers that are shown on the website. Deleting a server removes its row.
        Schema::create('simplify_embed_servers', function (Blueprint $table) {
            $table->unsignedInteger('server_id')->primary();
            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simplify_embed_servers');
        Schema::dropIfExists('simplify_embed_settings');
    }
};
