<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        $this->createGroupReviewStatsTable();
        $this->createOrganisationReviewStatsTable();
        $this->createShopReviewStatsTable();
        $this->createMasterProductCategoryReviewStatsTable();
        $this->createProductCategoryReviewStatsTable();
        $this->createMasterAssetReviewStatsTable();
        $this->createProductReviewStatsTable();
    }

    public function down(): void
    {
        Schema::dropIfExists('product_review_stats');
        Schema::dropIfExists('master_asset_review_stats');
        Schema::dropIfExists('product_category_review_stats');
        Schema::dropIfExists('master_product_category_review_stats');
        Schema::dropIfExists('shop_review_stats');
        Schema::dropIfExists('organisation_review_stats');
        Schema::dropIfExists('group_review_stats');
    }

    private function createGroupReviewStatsTable(): void
    {
        Schema::create('group_review_stats', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('group_id');
            $table->unique('group_id', 'grs_group_id_uq');
            $table->foreign('group_id', 'grs_group_id_fk')->references('id')->on('groups')->onUpdate('cascade')->onDelete('cascade');
            $this->addReviewStatsFields($table);
        });
    }

    private function createOrganisationReviewStatsTable(): void
    {
        Schema::create('organisation_review_stats', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('organisation_id');
            $table->unique('organisation_id', 'ors_organisation_id_uq');
            $table->foreign('organisation_id', 'ors_organisation_id_fk')->references('id')->on('organisations')->onUpdate('cascade')->onDelete('cascade');
            $this->addReviewStatsFields($table);
        });
    }

    private function createShopReviewStatsTable(): void
    {
        Schema::create('shop_review_stats', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('shop_id');
            $table->unique('shop_id', 'srs_shop_id_uq');
            $table->foreign('shop_id', 'srs_shop_id_fk')->references('id')->on('shops')->onUpdate('cascade')->onDelete('cascade');
            $this->addReviewStatsFields($table);
        });
    }

    private function createMasterProductCategoryReviewStatsTable(): void
    {
        Schema::create('master_product_category_review_stats', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('master_product_category_id');
            $table->unique('master_product_category_id', 'mprs_mpc_id_uq');
            $table->foreign('master_product_category_id', 'mprs_mpc_id_fk')->references('id')->on('master_product_categories')->onUpdate('cascade')->onDelete('cascade');
            $this->addReviewStatsFields($table);
        });
    }

    private function createProductCategoryReviewStatsTable(): void
    {
        Schema::create('product_category_review_stats', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('product_category_id');
            $table->unique('product_category_id', 'pcrs_pc_id_uq');
            $table->foreign('product_category_id', 'pcrs_pc_id_fk')->references('id')->on('product_categories')->onUpdate('cascade')->onDelete('cascade');
            $this->addReviewStatsFields($table);
        });
    }

    private function createMasterAssetReviewStatsTable(): void
    {
        Schema::create('master_asset_review_stats', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('master_asset_id');
            $table->unique('master_asset_id', 'mars_ma_id_uq');
            $table->foreign('master_asset_id', 'mars_ma_id_fk')->references('id')->on('master_assets')->onUpdate('cascade')->onDelete('cascade');
            $this->addReviewStatsFields($table);
        });
    }

    private function createProductReviewStatsTable(): void
    {
        Schema::create('product_review_stats', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('product_id');
            $table->unique('product_id', 'prs_product_id_uq');
            $table->foreign('product_id', 'prs_product_id_fk')->references('id')->on('products')->onUpdate('cascade')->onDelete('cascade');
            $this->addReviewStatsFields($table);
        });
    }

    private function addReviewStatsFields(Blueprint $table): void
    {
        $table->unsignedInteger('number_reviews')->default(0)->index();
        $table->unsignedInteger('number_reviews_pending')->default(0)->index();
        $table->unsignedInteger('number_reviews_approved')->default(0)->index();
        $table->unsignedInteger('number_reviews_rejected')->default(0)->index();

        $table->unsignedInteger('number_rating_1')->default(0)->index();
        $table->unsignedInteger('number_rating_2')->default(0)->index();
        $table->unsignedInteger('number_rating_3')->default(0)->index();
        $table->unsignedInteger('number_rating_4')->default(0)->index();
        $table->unsignedInteger('number_rating_5')->default(0)->index();

        $table->decimal('average_rating_main', 5, 2)->default(0)->index();
        $table->decimal('average_rating_a', 5, 2)->default(0)->index();
        $table->decimal('average_rating_b', 5, 2)->default(0)->index();
        $table->decimal('average_rating_c', 5, 2)->default(0)->index();
        $table->decimal('average_rating_d', 5, 2)->default(0)->index();
        $table->decimal('average_rating_e', 5, 2)->default(0)->index();

        $table->timestampsTz();
    }
};
