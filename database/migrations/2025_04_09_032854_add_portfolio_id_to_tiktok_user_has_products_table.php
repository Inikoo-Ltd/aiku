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
        Schema::table('tiktok_user_has_products', function (Blueprint $table) {
            $table->unsignedInteger('portfolio_id')->index()->after('productable_type')->nullable();
            $table->foreign('portfolio_id')->references('id')->on('portfolios');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tiktok_user_has_products', function (Blueprint $table) {
            $table->dropForeign(['portfolio_id']);
            $table->dropColumn(['portfolio_id']);
        });
    }
};
