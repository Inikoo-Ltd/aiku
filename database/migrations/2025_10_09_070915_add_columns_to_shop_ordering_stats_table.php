<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 09 Oct 2025 15:25:26 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_ordering_stats', function (Blueprint $table) {
            $table->decimal('average_historic_clv_amount', 16)->nullable();
            $table->decimal('average_historic_clv_amount_org_currency', 16)->nullable();
            $table->decimal('average_historic_clv_amount_grp_currency', 16)->nullable();

            $table->decimal('average_predicted_clv_amount', 16)->nullable();
            $table->decimal('average_predicted_clv_amount_org_currency', 16)->nullable();
            $table->decimal('average_predicted_clv_amount_grp_currency', 16)->nullable();

            $table->decimal('average_total_clv_amount', 16)->nullable();
            $table->decimal('average_total_clv_amount_org_currency', 16)->nullable();
            $table->decimal('average_total_clv_amount_grp_currency', 16)->nullable();

            $table->float('average_churn_interval')->nullable()->comment('in days');
            $table->float('average_churn_risk_prediction')->nullable();

            $table->float('average_time_between_orders')->nullable();
            $table->decimal('average_order_value', 16)->nullable();
            $table->dateTimeTz('expected_date_of_next_order')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('shop_ordering_stats', function (Blueprint $table) {
            $table->dropColumn('average_historic_clv_amount');
            $table->dropColumn('average_historic_clv_amount_grp_currency');
            $table->dropColumn('average_historic_clv_amount_org_currency');

            $table->dropColumn('average_predicted_clv_amount');
            $table->dropColumn('average_predicted_clv_amount_grp_currency');
            $table->dropColumn('average_predicted_clv_amount_org_currency');

            $table->dropColumn('average_total_clv_amount');
            $table->dropColumn('average_total_clv_amount_grp_currency');
            $table->dropColumn('average_total_clv_amount_org_currency');

            $table->dropColumn('average_churn_interval');
            $table->dropColumn('average_churn_risk_prediction');

            $table->dropColumn('average_time_between_orders');
            $table->dropColumn('average_order_value');
            $table->dropColumn('expected_date_of_next_order');
        });
    }
};
