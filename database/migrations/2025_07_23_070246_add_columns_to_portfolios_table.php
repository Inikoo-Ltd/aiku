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
            $table->boolean('has_valid_platform_product_id')->default(false);
            $table->boolean('exist_in_platform')->default(false);
            $table->boolean('platform_ready')->default(false)->comment('At location for shopify');
            $table->jsonb('possible_matches')->nullable();
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
            $table->dropColumn('has_valid_platform_product_id');
            $table->dropColumn('exist_in_platform');
            $table->dropColumn('platform_ready');
            $table->dropColumn('possible_matches');
        });
    }
};
