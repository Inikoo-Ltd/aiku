<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('stored_item_movements', function (Blueprint $table) {
            $table->id();

            $table->unsignedSmallInteger('stored_item_id')->index();
            $table->foreign('stored_item_id')->references('id')->on('stored_items')->onDelete('cascade');

            $table->unsignedSmallInteger('location_id')->index()->nullable();
            $table->foreign('location_id')->references('id')->on('locations')->onDelete('cascade');

            $table->string('type');
            $table->decimal('quantity')->default(0);

            $table->dateTimeTz('moved_at')->index();

            $table->timestampsTz();
        });
    }

    public function down()
    {
        Schema::dropIfExists('stored_item_movements');
    }
};
