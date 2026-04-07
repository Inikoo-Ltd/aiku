<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('reviewable_rating_stats', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->morphs('reviewable');
            $table->unsignedBigInteger('number_reviews_state_pending')->default(0);
            $table->unsignedBigInteger('number_reviews_state_approved')->default(0);
            $table->unsignedBigInteger('number_reviews_state_rejected')->default(0);
            $table->unsignedBigInteger('number_reviews_rating_1')->default(0);
            $table->unsignedBigInteger('number_reviews_rating_2')->default(0);
            $table->unsignedBigInteger('number_reviews_rating_3')->default(0);
            $table->unsignedBigInteger('number_reviews_rating_4')->default(0);
            $table->unsignedBigInteger('number_reviews_rating_5')->default(0);
            $table->unsignedBigInteger('number_reviews_like')->default(0);
            $table->unsignedBigInteger('reviews_count')->default(0);
            $table->decimal('rating_average', 5, 2)->default(0);
            $table->jsonb('rating_breakdown')->default('{}');
            $table->timestampTz('last_reviewed_at')->nullable();
            $table->timestampsTz();
            $table->unique(['reviewable_type', 'reviewable_id'], 'reviewable_rating_stats_unique_reviewable');
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('reviewable_rating_stats');
    }
};
