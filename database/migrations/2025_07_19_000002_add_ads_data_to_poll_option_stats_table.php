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
        Schema::table('poll_option_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_customer_purchases')->default(0);
            $table->decimal('total_customer_revenue', 16, 2)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('poll_option_stats', function (Blueprint $table) {
            $table->dropColumn(['number_customer_purchases', 'total_customer_revenue']);
        });
    }
};
