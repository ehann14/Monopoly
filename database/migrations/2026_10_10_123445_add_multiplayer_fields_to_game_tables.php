<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tambah kolom untuk multiplayer online
        Schema::table('game_rooms', function (Blueprint $table) {
            $table->string('name', 100)->nullable()->after('code'); // nama room opsional
            $table->unsignedTinyInteger('max_players')->default(4)->after('mode'); // batasan player
            $table->boolean('is_public')->default(true)->after('gm_announced'); // room bisa dilihat publik
        });
        
        // Update game_players untuk multiplayer
        Schema::table('game_players', function (Blueprint $table) {
            $table->timestamp('disconnected_at')->nullable()->after('last_seen_at');
            $table->timestamp('reconnected_at')->nullable()->after('disconnected_at');
        });
    }

    public function down(): void
    {
        Schema::table('game_players', function (Blueprint $table) {
            $table->dropColumn(['disconnected_at', 'reconnected_at']);
        });
        
        Schema::table('game_rooms', function (Blueprint $table) {
            $table->dropColumn(['name', 'max_players', 'is_public']);
        });
    }
};