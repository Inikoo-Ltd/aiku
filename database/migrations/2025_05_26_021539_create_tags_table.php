<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id');
            $table->foreign('group_id')->references('id')->on('groups')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedSmallInteger('organisation_id')->nullable();
            $table->foreign('organisation_id')->references('id')->on('organisations')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedSmallInteger('shop_id')->nullable();
            $table->foreign('shop_id')->references('id')->on('shops')->onUpdate('cascade')->onDelete('cascade');
            $table->string('slug')->unique();
            $table->string('name')->unique();
            $table->jsonb('data')->nullable();
            $table->unsignedInteger('number_models')->default(0);
            $table->timestamps();
            $table->index(['slug', 'name']);
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};
