<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('model_has_contents', function (Blueprint $table) {
            $table->increments('id');
            $table->string('type');
            $table->string('model_type');
            $table->unsignedInteger('model_id');
            $table->string('title');
            $table->string('text');
            $table->unsignedInteger('image_id')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->index(['model_type','model_id','type','image_id']);
            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('model_has_contents');
    }
};
