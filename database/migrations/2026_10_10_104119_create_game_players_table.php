<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // NULL = AI
            $table->string('name', 40);
            $table->boolean('is_ai')->default(false);
            $table->string('ai_difficulty', 10)->nullable();
            $table->unsignedTinyInteger('seat');                 // 0..3 = urutan giliran
            $table->string('token', 20);                         // warna/bidak
            $table->boolean('is_ready')->default(false);
            $table->boolean('is_connected')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->unsignedTinyInteger('position')->default(0);
            $table->integer('money')->default(1500);
            $table->boolean('in_jail')->default(false);
            $table->unsignedTinyInteger('jail_turns')->default(0);
            $table->unsignedTinyInteger('jail_cards')->default(0);
            $table->boolean('is_bankrupt')->default(false);
            $table->unsignedTinyInteger('bankrupt_order')->nullable();
            $table->timestamps();

            $table->unique(['game_room_id', 'seat']);
            $table->unique(['game_room_id', 'user_id']);         // cegah join ganda (NULL AI diabaikan MySQL)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_players');
    }
};