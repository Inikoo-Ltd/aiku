<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customer_stats', function (Blueprint $table) {
            $table->decimal('predicted_clv_amount_next_year', 16)->default(0);
            $table->decimal('predicted_clv_amount_next_year_org_currency', 16)->default(0);
            $table->decimal('predicted_clv_amount_next_year_grp_currency', 16)->default(0);
            $table->dateTime('first_order_date')->nullable();
            $table->integer('expected_remaining_lifespan_months')->default(0);
            $table->decimal('today_timeline_position', 5)->default(0);
            $table->decimal('next_order_timeline_position', 5)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_stats', function (Blueprint $table) {
            $table->dropColumn('predicted_clv_amount_next_year');
            $table->dropColumn('predicted_clv_amount_next_year_org_currency');
            $table->dropColumn('predicted_clv_amount_next_year_grp_currency');
            $table->dropColumn('first_order_date');
            $table->dropColumn('expected_remaining_lifespan_months');
            $table->dropColumn('today_timeline_position');
            $table->dropColumn('next_order_timeline_position');
        });
    }
};
