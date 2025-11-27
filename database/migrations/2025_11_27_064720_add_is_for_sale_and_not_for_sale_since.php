<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        // MASTER ASSETS
        Schema::table('master_assets', function (Blueprint $table) {
            $table->boolean('is_for_sale')->nullable();
            $table->timestampTz('not_for_sale_since')->nullable();
        });
        Schema::table('product_categories', function (Blueprint $table) {
            $table->boolean('is_for_sale')->nullable();
            $table->timestampTz('not_for_sale_since')->nullable();
        });
        Schema::table('master_product_categories', function (Blueprint $table) {
            $table->boolean('is_for_sale')->nullable();
            $table->timestampTz('not_for_sale_since')->nullable();
        });
        Schema::table('products', function (Blueprint $table) {
            $table->timestampTz('not_for_sale_since')->nullable();
        });

        DB::table('master_assets')->update([
            'is_for_sale' => DB::raw("CASE WHEN status = true THEN true ELSE false END")
        ]);
        DB::table('product_categories')->update([
            'is_for_sale' => DB::raw("CASE WHEN state != 'discontinued' THEN true ELSE false END")
        ]);
        DB::table('master_product_categories')->update([
            'is_for_sale' => DB::raw("CASE WHEN status = true THEN true ELSE false END")
        ]);
    }


    public function down(): void
    {
        Schema::table('master_assets', function (Blueprint $table) {
            $table->dropColumn(['is_for_sale', 'not_for_sale_since']);
        });

        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn(['is_for_sale', 'not_for_sale_since']);
        });

        Schema::table('master_product_categories', function (Blueprint $table) {
            $table->dropColumn(['is_for_sale', 'not_for_sale_since']);
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['not_for_sale_since']);
        });
    }
};
