<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('pallet_stored_items')) {
            Schema::create('pallet_stored_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('pallet_id');
                $table->foreign('pallet_id')->references('id')->on('pallets');
                $table->unsignedBigInteger('stored_item_id');
                $table->foreign('stored_item_id')->references('id')->on('stored_items');
                $table->decimal('quantity')->default(0);
                $table->decimal('damaged_quantity')->default(0);
                $table->string('source')->default(0);
                $table->timestampsTz();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('pallet_stored_items');
    }
};
