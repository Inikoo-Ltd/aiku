<?php

use App\Enums\Catalogue\Review\ReviewReplyReplierTypeEnum;
use App\Enums\Catalogue\Review\ReviewStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('review_replies', function (Blueprint $table) {
            $table->id();
            /*
            * Polymorphic — reviewable_type values in your codebase:
            *   'product_reviews'
            *   'shop_reviews'
            *   'product_category_reviews'
            */
            $table->string('reviewable_type')->index();
            $table->unsignedBigInteger('reviewable_id')->index();
            $table->index(['reviewable_type', 'reviewable_id'], 'rr_reviewable_idx');

            $table->unsignedSmallInteger('organisation_id')->nullable()->index();
            $table->foreign('organisation_id', 'rr_organisation_id_fk')
                ->references('id')->on('organisations')->nullOnDelete();

            $table->unsignedSmallInteger('user_id')->nullable()->index();
            $table->foreign('user_id', 'rr_user_id_fk')
                ->references('id')->on('users')->nullOnDelete();

            $table->enum('replier_type', ReviewReplyReplierTypeEnum::values())
                ->default(ReviewReplyReplierTypeEnum::Merchant->value)->index();

            $table->text('body');
            $table->boolean('is_public')->default(true)->index();
            $table->enum('status', ReviewStatusEnum::values())
                ->default(ReviewStatusEnum::Approved->value)->index();
            $table->timestampsTz();

            $table->unique(
                ['reviewable_type', 'reviewable_id', 'replier_type'],
                'rr_one_reply_per_type_uq'
            );
            $table->index(
                ['reviewable_type', 'reviewable_id', 'is_public'],
                'rr_public_lookup_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_replies');
    }
};
