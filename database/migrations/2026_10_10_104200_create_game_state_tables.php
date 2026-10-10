<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Status dinamis setiap petak properti dalam satu game.
        Schema::create('property_ownerships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_room_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('tile_id');
            $table->foreignId('owner_player_id')->nullable()->constrained('game_players')->nullOnDelete();
            $table->unsignedTinyInteger('houses')->default(0);   // 0..4
            $table->boolean('has_hotel')->default(false);
            $table->boolean('is_mortgaged')->default(false);
            $table->timestamps();

            $table->unique(['game_room_id', 'tile_id']);
            $table->index(['game_room_id', 'owner_player_id']);
        });

        // Urutan kartu per game. Definisi kartu ada di config/monopoly.php.
        Schema::create('game_card_decks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_room_id')->constrained()->cascadeOnDelete();
            $table->string('deck', 20);                          // chance | chest
            $table->json('order');                               // daftar card_id yang sudah diacak server
            $table->unsignedSmallInteger('pointer')->default(0);
            $table->json('held')->nullable();                    // kartu bebas penjara yang sedang dipegang
            $table->timestamps();

            $table->unique(['game_room_id', 'deck']);
        });

        // Log PUBLIK. Tidak boleh berisi aksi rahasia Game Master.
        Schema::create('game_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_room_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('state_version');
            $table->string('type', 40);
            $table->string('message', 500);
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['game_room_id', 'id']);
        });

        // Audit log PRIVAT owner. Hanya diakses lewat policy owner.
        Schema::create('game_master_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('action', 60);
            $table->unsignedBigInteger('target_player_id')->nullable();
            $table->boolean('is_secret')->default(true);
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['game_room_id', 'id']);
        });

        // Idempotensi: satu kunci hanya diproses sekali per pengguna per room.
        Schema::create('game_action_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('idempotency_key', 64);
            $table->string('action', 60);
            $table->json('response')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['game_room_id', 'user_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_action_requests');
        Schema::dropIfExists('game_master_audit_logs');
        Schema::dropIfExists('game_logs');
        Schema::dropIfExists('game_card_decks');
        Schema::dropIfExists('property_ownerships');
    }
};