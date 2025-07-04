<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('printers', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->jsonb('capabilities')->nullable();
            $table->jsonb('trays')->nullable();
            $table->string('status')->default('offline');
            $table->boolean('is_online')->default(false);
            $table->unsignedInteger('computer_id')->index()->nullable();
            $table->foreign('computer_id')->references('id')->on('computers');
            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('printers');
    }
};
