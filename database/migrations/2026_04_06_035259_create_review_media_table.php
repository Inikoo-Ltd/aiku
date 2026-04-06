<?php

use App\Enums\Catalogue\Review\ReviewMediaTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('review_media', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('review_id')->index();
            $table->foreign('review_id')->references('id')->on('reviews')->cascadeOnDelete();
            $table->unsignedInteger('media_id')->index();
            $table->foreign('media_id')->references('id')->on('media')->cascadeOnDelete();
            $table->enum('type', ReviewMediaTypeEnum::values())->index()->default(ReviewMediaTypeEnum::IMAGE->value);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->jsonb('meta')->default('{}');
            $table->timestampsTz();
            $table->unique(['review_id', 'media_id']);
            $table->index(['review_id', 'sort_order']);
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('review_media');
    }
};
