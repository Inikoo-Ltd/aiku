<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->increments('id');
            $table->string('slug')->unique()->collation('und_ns');
            $table->string('reference')->index();
            $table->string('name')->index();
            $table->unsignedInteger('number_models')->default(0);

            $table->unsignedInteger('image_id')->nullable();
            $table->foreign('image_id')->references('id')->on('media')->onDelete('cascade');
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};
