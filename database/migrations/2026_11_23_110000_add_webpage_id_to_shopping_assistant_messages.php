<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('shopping_assistant_messages', function (Blueprint $table) {
            $table->unsignedInteger('webpage_id')->nullable();
            $table->jsonb('trail_webpage_ids')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('shopping_assistant_messages', function (Blueprint $table) {
            $table->dropColumn(['webpage_id', 'trail_webpage_ids']);
        });
    }
};
