<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('handled_in_aurora')->default(false);
        });
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->boolean('handled_in_aurora')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('handled_in_aurora');
        });
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->dropColumn('handled_in_aurora');
        });
    }
};
