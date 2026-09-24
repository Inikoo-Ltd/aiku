<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('picking_sessions', function (Blueprint $table) {
            $table->boolean('is_waiting_ready')->default(false)->index();
        });
    }

    public function down(): void
    {
        Schema::table('picking_sessions', function (Blueprint $table) {
            $table->dropColumn('is_waiting_ready');
        });
    }
};
