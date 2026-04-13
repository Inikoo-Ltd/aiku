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
        Schema::create('product_category_reviews', function (Blueprint $table) {
            $table->id();
            $this->groupOrgRelationship($table);
            $table->unsignedInteger('customer_id')->nullable()->index();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->unsignedInteger('order_id')->nullable()->index();
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
            $table->unsignedSmallInteger('master_product_category_id')->nullable()->index();
            $table->foreign('master_product_category_id')->references('id')->on('master_product_categories')->nullOnDelete();
            $table->unsignedSmallInteger('product_category_id')->nullable()->index();
            $table->foreign('product_category_id')->references('id')->on('product_categories')->nullOnDelete();
            $table->unsignedTinyInteger('rating_main')->index();
            $table->unsignedTinyInteger('rating_a')->index()->nullable();
            $table->unsignedTinyInteger('rating_b')->index()->nullable();
            $table->unsignedTinyInteger('rating_c')->index()->nullable();
            $table->unsignedTinyInteger('rating_d')->index()->nullable();
            $table->unsignedTinyInteger('rating_e')->index()->nullable();
            $table->timestampTz('show_after')->nullable()->index();
            $table->enum('status', ReviewStatusEnum::values())->index()->default(ReviewStatusEnum::Pending->value);
            $table->string('title')->nullable()->index();
            $table->text('message')->nullable();
            $table->unsignedInteger('like_count')->default(0);
            $table->jsonb('meta')->default('{}');
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->index(['status', 'created_at'], 'product_category_reviews_status_created_idx');
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('product_category_reviews');
    }
};
