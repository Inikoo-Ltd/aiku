<?php

/*
 * Author: Oggie Sutrisna
 * Created: Thu, 18 Dec 2025 15:37:00 Makassar Time
 * Description: Create order_has_returns pivot table to link orders with their returns
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('order_has_returns', function (Blueprint $table) {
            $table->unsignedInteger('order_id');
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->unsignedInteger('return_id');
            $table->foreign('return_id')->references('id')->on('returns')->cascadeOnDelete();
            $table->timestampsTz();

            $table->primary(['order_id', 'return_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_has_returns');
    }
};
