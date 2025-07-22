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
        Schema::table('shop_crm_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_traffic_sources')->default(0);
        });
        Schema::table('organisation_crm_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_traffic_sources')->default(0);
        });
        Schema::table('group_crm_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_traffic_sources')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('shop_crm_stats', function (Blueprint $table) {
            $table->dropColumn('number_traffic_sources');
        });
        Schema::table('organisation_crm_stats', function (Blueprint $table) {
            $table->dropColumn('number_traffic_sources');
        });
        Schema::table('group_crm_stats', function (Blueprint $table) {
            $table->dropColumn('number_traffic_sources');
        });
    }
};
