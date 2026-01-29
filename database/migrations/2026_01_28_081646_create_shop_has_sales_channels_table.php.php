<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shop_has_sales_channels', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->foreignId('shop_id')->index()->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('sales_channel_id')->index()->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->timestamps();
            $table->primary(['shop_id', 'sales_channel_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_has_sales_channels');
    }
};
