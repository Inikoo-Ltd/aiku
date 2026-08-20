<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('labeling_guides', function (Blueprint $table) {
            $table->id();

            $table->unsignedSmallInteger('product_category_id')->index();
            $table->foreign('product_category_id')->references('id')->on('product_categories')->nullOnDelete();

            $table->string('filename')->nullable()->comment('Original filename');
            $table->string('path')->comment('Storage path');
            $table->unsignedInteger('file_size')->comment('Size in bytes');
            $table->string('checksum')->nullable()->comment('MD5 checksum for integrity');

            $table->unsignedInteger('uploaded_by')->nullable();
            $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
            $table->timestampTz('uploaded_at')->nullable();

            $table->timestampsTz();

            $table->unique(['product_category_id', 'id']);
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('labeling_guides');
    }
};
