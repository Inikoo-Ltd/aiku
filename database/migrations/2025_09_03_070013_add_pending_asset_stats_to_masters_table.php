<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('master_product_category_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_pending_master_assets')->default(0);
        });
        Schema::table('master_shop_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_master_families_with_pending_master_assets')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('master_product_category_stats', function (Blueprint $table) {
            $table->dropColumn('number_pending_master_assets')->default(0);
        });
        Schema::table('master_shop_stats', function (Blueprint $table) {
            $table->dropColumn('number_master_families_with_pending_master_assets')->default(0);
        });
    }
};
