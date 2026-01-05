<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('debug_stock_updates', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('org_stock_id');
            $table->string('slug')->nullable();
            $table->decimal('old_quantity_available', 16, 3)->nullable();
            $table->decimal('new_quantity_available', 16, 3)->nullable();
            $table->decimal('old_quantity_in_locations', 16, 3)->nullable();
            $table->decimal('new_quantity_in_locations', 16, 3)->nullable();
            $table->integer('product_count')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debug_stock_updates');
    }
};
