<?php

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
            $table->string('review_context', 255)->index();
            $table->enum('dimension', ['a', 'b', 'c', 'd', 'e'])->index();
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
