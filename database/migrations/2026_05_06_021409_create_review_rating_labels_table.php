<?php

use App\Enums\Catalogue\Review\ReviewContextEnum;
use App\Enums\Catalogue\Review\ReviewRatingDimensionEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('review_rating_labels', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('model_type', 255)->index();
            $table->unsignedSmallInteger('model_id')->index();
            $table->enum('review_context', ReviewContextEnum::values())->index();
            $table->enum('dimension', ReviewRatingDimensionEnum::values())->index();
            $table->string('label');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_required')->default(false);
            $table->decimal('weight', 4, 2)->default(1.00);
            $table->timestampsTz();

            $table->unique(
                ['model_type', 'model_id', 'review_context', 'dimension'],
                'rrl_model_context_dimension_uq'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_rating_labels');
    }
};
