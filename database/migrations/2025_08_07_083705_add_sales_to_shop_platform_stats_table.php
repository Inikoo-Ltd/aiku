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
        Schema::table('shop_platform_stats', function (Blueprint $table) {
            $table->decimal('sales', 16)->default(0);
            $table->decimal('sales_org_currency', 16)->default(0);
            $table->decimal('sales_grp_currency', 16)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('shop_platform_stats', function (Blueprint $table) {
            $table->dropColumn(['sales', 'sales_org_currency', 'sales_grp_currency']);
        });
    }
};
