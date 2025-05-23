<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('model_has_tags', function (Blueprint $table) {
            $table->increments('id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->unsignedBigInteger('tag_id');
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('model_has_tags');
    }
};
