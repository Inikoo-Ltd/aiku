<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('product_development_components', function (Blueprint $table) {
            $table->timestampTz('purchase_confirmed_at')->nullable();
            $table->unsignedInteger('purchase_confirmed_by_id')->nullable();
            $table->foreign('purchase_confirmed_by_id')->references('id')->on('users');
            $table->string('purchase_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('product_development_components', function (Blueprint $table) {
            $table->dropForeign(['purchase_confirmed_by_id']);
            $table->dropColumn(['purchase_confirmed_at', 'purchase_confirmed_by_id', 'purchase_note']);
        });
    }
};
