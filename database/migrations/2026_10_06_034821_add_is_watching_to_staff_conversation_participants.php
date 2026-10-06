<?php

/*
 * author Louis Perez
 * created on 06-10-2026
 * GitHub: https://github.com/louis-perez
 * copyright 2026
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('staff_conversation_participants', function (Blueprint $table) {
            $table->boolean('is_watching')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('staff_conversation_participants', function (Blueprint $table) {
            $table->dropColumn('is_watching');
        });
    }
};
