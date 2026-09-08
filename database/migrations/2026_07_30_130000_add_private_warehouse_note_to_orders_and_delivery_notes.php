<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->text('private_warehouse_note')->nullable();
        });
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->text('private_warehouse_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('private_warehouse_note');
        });
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->dropColumn('private_warehouse_note');
        });
    }
};
