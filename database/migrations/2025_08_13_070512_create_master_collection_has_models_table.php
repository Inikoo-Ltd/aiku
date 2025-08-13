<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('master_collection_has_models', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('master_collection_id')->index();
            $table->foreign('master_collection_id')->references('id')->on('master_collections');
            $table->string('model_type')->index();
            $table->string('type')->index()->default('direct');
            $table->unsignedBigInteger('model_id')->index();
            $table->timestampsTz();
            $table->index(['model_type', 'model_id']);
            $table->unique(['master_collection_id', 'model_type', 'model_id']);
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('master_collection_has_models');
    }
};
