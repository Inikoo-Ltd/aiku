<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('model_has_brands', function (Blueprint $table) {
            $table->increments('id');
            $table->string('model_type')->index();
            $table->unsignedInteger('model_id')->index();
            $table->unsignedInteger('brand_id')->index();
            $table->foreign('brand_id')->references('id')->on('brands')->onDelete('cascade');

            $table->unique(['model_type', 'model_id', 'brand_id']);
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('model_has_brands');
    }
};
