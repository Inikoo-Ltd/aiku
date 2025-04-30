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
        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('amount_in_basket', 16)->default(0);
            $table->decimal('amount_in_basket_org_currency', 16)->default(0);
            $table->decimal('amount_in_basket_grp_currency', 16)->default(0);
            $table->unsignedInteger('current_order_in_basket_id')->nullable()->index();
            $table->foreign('current_order_in_basket_id')->references('id')->on('orders');
        });
        Schema::table('customer_clients', function (Blueprint $table) {
            $table->decimal('amount_in_basket', 16)->default(0);
            $table->decimal('amount_in_basket_org_currency', 16)->default(0);
            $table->decimal('amount_in_basket_grp_currency', 16)->default(0);
            $table->unsignedInteger('current_order_in_basket_id')->nullable()->index();
            $table->foreign('current_order_in_basket_id')->references('id')->on('orders');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['amount_in_basket', 'amount_in_basket_org_currency', 'amount_in_basket_grp_currency']);
            $table->dropForeign(['current_order_in_basket_id']);
            $table->dropColumn('current_order_in_basket_id');
        });
        Schema::table('customer_clients', function (Blueprint $table) {
            $table->dropColumn(['amount_in_basket', 'amount_in_basket_org_currency', 'amount_in_basket_grp_currency']);
            $table->dropForeign(['current_order_in_basket_id']);
            $table->dropColumn('current_order_in_basket_id');
        });
    }
};
