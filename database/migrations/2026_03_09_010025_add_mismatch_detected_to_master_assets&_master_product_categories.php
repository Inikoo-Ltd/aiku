<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('master_assets', function (Blueprint $table) {
            $table->boolean('mismatch_detected')->default(false)->comment('Have a mismatch trade unit data (picking quantity, linked trade unit) with one or more of its children product');
        });

        Schema::table('master_product_categories', function (Blueprint $table) {
            $table->boolean('mismatch_detected')->default(false)->comment('One of master products under it has a mismatch trade unit data (picking quantity, linked trade unit) with one or more of its children product');
        });

        Schema::table('master_product_category_stats', function (Blueprint $table) {
            $table->unsignedBigInteger('number_mismatch_detected')->default(0)->comment('Amount of master products related to master product categories that has mismatch trade unit data');
        });

        Schema::table('master_shop_stats', function (Blueprint $table) {
            $table->unsignedBigInteger('number_mismatched_master_families')->default(0)->comment('Amount of master product categories that has mismatch_detected = true');
            $table->unsignedBigInteger('number_mismatched_master_products')->default(0)->comment('Amount of master products that has mismatch_detected = true');
        });
    }


    public function down(): void
    {
        Schema::table('master_assets', function (Blueprint $table) {
            $table->dropColumn(['mismatch_detected']);
        });

        Schema::table('master_product_categories', function (Blueprint $table) {
            $table->dropColumn(['mismatch_detected']);
        });

        Schema::table('master_product_category_stats', function (Blueprint $table) {
            $table->dropColumn(['number_mismatch_detected']);
        });

        Schema::table('master_shop_stats', function (Blueprint $table) {
            $table->dropColumn(['number_mismatched_master_families', 'number_mismatched_master_products']);
        });
    }
};
