<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('movement_pallets')) {
            Schema::create('movement_pallets', function (Blueprint $table) {
                $table->id();

                $table->unsignedSmallInteger('pallet_id')->index();
                $table->foreign('pallet_id')->references('id')->on('pallets')->onDelete('cascade');

                $table->unsignedSmallInteger('location_from_id')->index()->nullable();
                $table->foreign('location_from_id')->references('id')->on('locations')->onDelete('cascade');

                $table->unsignedSmallInteger('location_to_id')->index()->nullable();
                $table->foreign('location_to_id')->references('id')->on('locations')->onDelete('cascade');

                $table->dateTimeTz('moved_at')->index();

                $table->timestampsTz();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('movement_pallets');
    }
};
