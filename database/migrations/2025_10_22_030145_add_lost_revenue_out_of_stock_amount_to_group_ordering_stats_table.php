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
        Schema::table('group_ordering_stats', function (Blueprint $table) {
            $table->decimal('revenue_amount', 16)->default(0);
            $table->decimal('revenue_amount_org_currency', 16)->default(0);
            $table->decimal('revenue_amount_grp_currency', 16)->default(0);

            $table->decimal('lost_revenue_out_of_stock_amount', 16)->default(0);
            $table->decimal('lost_revenue_out_of_stock_amount_org_currency', 16)->default(0);
            $table->decimal('lost_revenue_out_of_stock_amount_grp_currency', 16)->default(0);

            $table->decimal('lost_revenue_replacements_amount', 16)->default(0);
            $table->decimal('lost_revenue_replacements_amount_org_currency', 16)->default(0);
            $table->decimal('lost_revenue_replacements_amount_grp_currency', 16)->default(0);

            $table->decimal('lost_revenue_compensations_amount', 16)->default(0);
            $table->decimal('lost_revenue_compensations_amount_org_currency', 16)->default(0);
            $table->decimal('lost_revenue_compensations_amount_grp_currency', 16)->default(0);

            $table->decimal('lost_revenue_other_amount', 16)->default(0);
            $table->decimal('lost_revenue_other_amount_org_currency', 16)->default(0);
            $table->decimal('lost_revenue_other_amount_grp_currency', 16)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('group_ordering_stats', function (Blueprint $table) {
            $table->dropColumn('revenue_amount');
            $table->dropColumn('revenue_amount_org_currency');
            $table->dropColumn('revenue_amount_grp_currency');

            $table->dropColumn('lost_revenue_out_of_stock_amount');
            $table->dropColumn('lost_revenue_out_of_stock_amount_org_currency');
            $table->dropColumn('lost_revenue_out_of_stock_amount_grp_currency');

            $table->dropColumn('lost_revenue_replacements_amount');
            $table->dropColumn('lost_revenue_replacements_amount_org_currency');
            $table->dropColumn('lost_revenue_replacements_amount_grp_currency');

            $table->dropColumn('lost_revenue_compensations_amount');
            $table->dropColumn('lost_revenue_compensations_amount_org_currency');
            $table->dropColumn('lost_revenue_compensations_amount_grp_currency');

            $table->dropColumn('lost_revenue_other_amount');
            $table->dropColumn('lost_revenue_other_amount_org_currency');
            $table->dropColumn('lost_revenue_other_amount_grp_currency');
        });
    }
};
