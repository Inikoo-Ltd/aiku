<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->jsonb('post_submit_modification_data')->nullable();
        });
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('submitted_quantity_ordered', 16, 3)->default(0);
        });
        Schema::table('delivery_note_items', function (Blueprint $table) {
            $table->decimal('original_quantity_required', 16, 3)->default(0);
        });
    }


    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['post_submit_modification_data']);
        });
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['submitted_quantity_ordered']);
        });
        Schema::table('delivery_note_items', function (Blueprint $table) {
            $table->dropColumn(['original_quantity_required']);
        });
    }
};
