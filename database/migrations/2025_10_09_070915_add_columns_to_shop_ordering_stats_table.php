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
        Schema::table('shop_ordering_stats', function (Blueprint $table) {
            $table->decimal('historic_clv_amount', 16, 2)->nullable();
            $table->decimal('historic_clv_amount_grp_currency')->nullable();
            $table->decimal('historic_clv_amount_org_currency')->nullable();

            $table->decimal('predicted_clv_amount', 16, 2)->nullable();
            $table->decimal('predicted_clv_amount_grp_currency')->nullable();
            $table->decimal('predicted_clv_amount_org_currency')->nullable();

            $table->float('churn_interval')->nullable()->comment('In Day');
            $table->float('churn_risk_prediction')->nullable();
            $table->float('average_time_between_orders')->nullable();
            $table->decimal('average_order_value')->nullable();

            $table->dateTimeTz('expected_date_of_next_order')->nullable();


            $table->decimal('total_clv_amount', 16, 2)->nullable();
            $table->decimal('total_clv_amount_grp_currency')->nullable();
            $table->decimal('total_clv_amount_org_currency')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('shop_ordering_stats', function (Blueprint $table) {
            $table->dropColumn('historic_clv_amount');
            $table->dropColumn('historic_clv_amount_grp_currency');
            $table->dropColumn('historic_clv_amount_org_currency');
            $table->dropColumn('predicted_clv_amount');
            $table->dropColumn('predicted_clv_amount_grp_currency');
            $table->dropColumn('predicted_clv_amount_org_currency');
            $table->dropColumn('churn_interval');
            $table->dropColumn('churn_risk_prediction');
            $table->dropColumn('average_time_between_orders');
            $table->dropColumn('average_order_value');
            $table->dropColumn('expected_date_of_next_order');
            $table->dropColumn('total_clv_amount');
            $table->dropColumn('total_clv_amount_grp_currency');
            $table->dropColumn('total_clv_amount_org_currency');
        });
    }
};
