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
        Schema::table('portfolios', function (Blueprint $table) {
            $table->renameColumn('shopify_product_id', 'platform_product_id');
            $table->renameColumn('shopify_handle', 'platform_handle');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('portfolios', function (Blueprint $table) {
            $table->renameColumn('platform_product_id', 'shopify_product_id');
            $table->renameColumn('platform_handle', 'shopify_handle');
        });
    }
};
