<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('computers', function (Blueprint $table) {
            $table->increments("id");
            $table->unsignedSmallInteger('group_id')->index()->nullable();
            $table->foreign('group_id')->references('id')->on('groups');

            $table->unsignedBigInteger('organisation_id')->index()->nullable();
            $table->foreign('organisation_id')->references('id')->on('organisations');

            $table->unsignedBigInteger('shop_id')->index()->nullable();
            $table->foreign('shop_id')->references('id')->on('shops');


            $table->unsignedBigInteger('shipment_id')->index()->nullable();
            $table->foreign('shipment_id')->references('id')->on('shipments');

            $table->string('slug')->unique()->collation('und_ns');
            $table->string('name')->index();
            $table->string('serial_number')->index()->nullable();


            $table->timestampsTz();

        });
    }


    public function down(): void
    {
        Schema::dropIfExists('computers');
    }
};
