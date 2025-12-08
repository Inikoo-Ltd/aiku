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
        Schema::table('invoice_category_sales_metrics', function (Blueprint $table) {
            $table->decimal('sales_org_currency', 16)->default(0.00)->after('sales_grp_currency');
            $table->decimal('revenue_org_currency', 16)->default(0.00)->after('revenue_grp_currency');
            $table->decimal('lost_revenue_org_currency', 16)->default(0.00)->after('lost_revenue_grp_currency');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('invoice_category_sales_metrics', function (Blueprint $table) {
            $table->dropColumn([
                'sales_org_currency',
                'revenue_org_currency',
                'lost_revenue_org_currency',
            ]);
        });
    }
};
