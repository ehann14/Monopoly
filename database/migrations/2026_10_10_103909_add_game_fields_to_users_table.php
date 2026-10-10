<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 30)->nullable()->unique()->after('name');
            $table->string('email')->nullable()->change();
            $table->string('role', 20)->default('player')->after('password');
            // Trik "satu owner saja": NULL boleh banyak, tetapi nilai 1 hanya boleh satu baris.
            $table->unsignedTinyInteger('owner_flag')->nullable()->unique()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['owner_flag']);
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'role', 'owner_flag']);
        });
    }
};