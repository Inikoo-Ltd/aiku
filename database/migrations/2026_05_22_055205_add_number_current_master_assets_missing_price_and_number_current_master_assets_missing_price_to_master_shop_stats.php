<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('master_shop_stats', function (Blueprint $table) {
            $table->unsignedBigInteger('number_current_master_assets_missing_price_or_rrp')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('master_shop_stats', function (Blueprint $table) {
            $table->dropColumn([
                'number_current_master_assets_missing_price_or_rrp',
            ]);
        });
    }
};
