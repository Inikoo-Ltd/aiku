<?php

/*
 * Author: Oggie Sutrisna
 * Created: Wed, 18 Dec 2025 13:50:00 Makassar Time
 * Description: Create return_order pivot table to link returns with orders
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('return_order', function (Blueprint $table) {
            $table->unsignedInteger('return_id');
            $table->foreign('return_id')->references('id')->on('returns')->cascadeOnDelete();
            $table->unsignedInteger('order_id');
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->timestampsTz();

            $table->primary(['return_id', 'order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_order');
    }
};
