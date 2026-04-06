<?php

use App\Enums\Catalogue\Review\ReviewStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups')->nullOnDelete();
            $table->unsignedSmallInteger('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations')->nullOnDelete();
            $table->unsignedSmallInteger('shop_id')->nullable()->index();
            $table->foreign('shop_id')->references('id')->on('shops')->nullOnDelete();
            $table->unsignedInteger('customer_id')->nullable()->index();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->morphs('reviewable');
            $table->enum('status', ReviewStatusEnum::values())->index()->default(ReviewStatusEnum::Pending->value);
            $table->unsignedTinyInteger('rating')->index();
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->boolean('is_verified_purchase')->default(false)->index();
            $table->unsignedInteger('helpful_count')->default(0);
            $table->jsonb('meta')->default('{}');
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->index(['reviewable_type', 'reviewable_id', 'status', 'created_at'], 'reviews_reviewable_status_created_idx');
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
