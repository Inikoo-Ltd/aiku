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
        Schema::table('customer_stats', function (Blueprint $table) {
            $table->decimal('amount_in_basket', 16)->default(0);
            $table->decimal('amount_in_basket_org_currency', 16)->default(0);
            $table->decimal('amount_in_basket_grp_currency', 16)->default(0);
        });
        Schema::table('customer_client_stats', function (Blueprint $table) {
            $table->decimal('amount_in_basket', 16)->default(0);
            $table->decimal('amount_in_basket_org_currency', 16)->default(0);
            $table->decimal('amount_in_basket_grp_currency', 16)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customer_stats', function (Blueprint $table) {
            $table->dropColumn(['amount_in_basket', 'amount_in_basket_org_currency', 'amount_in_basket_grp_currency']);
        });
        Schema::table('customer_client_stats', function (Blueprint $table) {
            $table->dropColumn(['amount_in_basket', 'amount_in_basket_org_currency', 'amount_in_basket_grp_currency']);
        });
    }
};
