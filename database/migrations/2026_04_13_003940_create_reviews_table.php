<?php

use App\Enums\Catalogue\Review\ReviewStatusEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->nullable()->index();
            $table->foreign('shop_id')->references('id')->on('shops')->nullOnDelete();
            $table->unsignedInteger('customer_id')->nullable()->index();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();

            $table->string('scope')->index(); // Scope of the review, e.g. 'product', 'family', 'overall'

            $table->unsignedInteger('order_id')->nullable()->index();
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();

            $table->unsignedBigInteger('master_product_category_id')->nullable()->index();
            $table->foreign('master_product_category_id')->references('id')->on('master_product_categories')->nullOnDelete();
            $table->unsignedBigInteger('product_category_id')->nullable()->index();
            $table->foreign('product_category_id')->references('id')->on('product_categories')->nullOnDelete();

            $table->unsignedBigInteger('master_product_id')->nullable()->index();
            $table->foreign('master_product_id')->references('id')->on('master_assets')->nullOnDelete();
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();

            $table->decimal('rating_main', 5)->index();
            $table->unsignedTinyInteger('rating_a')->index()->nullable();
            $table->unsignedTinyInteger('rating_b')->index()->nullable();
            $table->unsignedTinyInteger('rating_c')->index()->nullable();
            $table->unsignedTinyInteger('rating_d')->index()->nullable();
            $table->unsignedTinyInteger('rating_e')->index()->nullable();
            $table->timestampTz('show_after')->nullable()->index();
            $table->timestampTz('published_at')->nullable()->index();


            $table->boolean('is_public')->default(true)->index();
            $table->enum('review_status', ReviewStatusEnum::values())->index()->default(ReviewStatusEnum::PENDING->value);
            $table->string('title')->nullable()->index();
            $table->text('message')->nullable();
            $table->unsignedInteger('like_count')->default(0);
            $table->jsonb('meta')->default('{}');
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->index(['status', 'created_at'], 'shop_reviews_status_created_idx');
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('shop_reviews');
    }
};
