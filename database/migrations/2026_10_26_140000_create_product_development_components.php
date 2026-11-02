<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('product_development_components', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('product_development_id')->index();
            $table->foreign('product_development_id')->references('id')->on('product_developments')->cascadeOnDelete();
            $table->unsignedInteger('product_development_item_id')->index();
            $table->foreign('product_development_item_id')->references('id')->on('product_development_items')->cascadeOnDelete();
            $table->unsignedInteger('trade_unit_id')->index();
            $table->foreign('trade_unit_id')->references('id')->on('trade_units');
            $table->decimal('quantity', 12, 3)->default(0);
            $table->string('note')->nullable();
            $table->string('status')->default('pending')->index();
            $table->unsignedInteger('approved_by_id')->nullable();
            $table->foreign('approved_by_id')->references('id')->on('users');
            $table->timestampTz('approved_at')->nullable();
            $table->timestampsTz();
            $table->unique(['product_development_item_id', 'trade_unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_development_components');
    }
};
