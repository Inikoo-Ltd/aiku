<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('bundle_items', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('bundle_id');
            $table->foreign('bundle_id')->references('id')->on('bundles')->onDelete('cascade');
            $table->morphs('item');
            $table->integer('quantity');

            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('bundle_items');
    }
};
