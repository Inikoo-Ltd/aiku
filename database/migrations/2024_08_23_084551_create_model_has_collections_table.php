<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_has_collections', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('collection_id');
            $table->foreign('collection_id')->references('id')->on('collections');
            $table->string('model_type');
            $table->unsignedInteger('model_id');
            $table->string('type')->nullable();
            $table->index(['model_type', 'model_id']);
            $table->unique(['collection_id', 'model_type', 'model_id']);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_has_collection');
    }
};
