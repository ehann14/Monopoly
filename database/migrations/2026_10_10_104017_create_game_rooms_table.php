<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_rooms', function (Blueprint $table) {
            $table->id();
            $table->string('code', 8)->unique();
            $table->foreignId('host_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('mode', 20)->default('multiplayer');      // multiplayer | ai
            $table->string('status', 20)->default('waiting');        // waiting|playing|paused|finished|cancelled
            $table->string('ai_difficulty', 10)->nullable();         // easy | hard
            $table->json('rules');                                   // salinan konfigurasi aturan saat room dibuat
            $table->boolean('gm_enabled')->default(false);           // Game Master Mode (PUBLIK: terlihat semua pemain)
            $table->boolean('gm_announced')->default(false);         // host sudah mengumumkan aturan khusus
            $table->unsignedBigInteger('current_player_id')->nullable();
            $table->string('turn_phase', 30)->default('await_roll'); // await_roll|await_buy|await_debt|await_end|...
            $table->unsignedInteger('turn_number')->default(0);
            $table->unsignedTinyInteger('doubles_count')->default(0);
            $table->json('last_dice')->nullable();
            $table->json('pending')->nullable();                     // aksi tertunda publik (mis. tawaran beli, utang)
            $table->unsignedBigInteger('state_version')->default(0); // optimistic concurrency
            $table->unsignedBigInteger('winner_player_id')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        // Data Game Master dipisah supaya tidak pernah ikut ter-serialize ke publik.
        Schema::create('game_master_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_room_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('dice_override')->nullable();     // {target_player_id, die1, die2, remaining_uses}
            $table->json('lucky')->nullable();             // {target_player_id, kind, remaining_turns}
            $table->json('rule_overrides')->nullable();
            $table->boolean('chaos_mode')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_master_states');
        Schema::dropIfExists('game_rooms');
    }
};