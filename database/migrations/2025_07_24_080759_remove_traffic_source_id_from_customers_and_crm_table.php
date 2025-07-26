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
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['traffic_source_id']);
            $table->dropColumn('traffic_source_id');
        });

        Schema::table('group_crm_stats', function (Blueprint $table) {
            $table->dropColumn('number_traffic_sources');
        });

        Schema::table('shop_crm_stats', function (Blueprint $table) {
            $table->dropColumn('number_traffic_sources');
        });

        Schema::table('organisation_crm_stats', function (Blueprint $table) {
            $table->dropColumn('number_traffic_sources');
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
            $table->unsignedInteger('traffic_source_id')->nullable()->after('id');
            $table->foreign('traffic_source_id')->references('id')->on('traffic_sources')->nullOnDelete();
        });

        Schema::table('group_crm_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_traffic_sources')->default(0);
        });

        Schema::table('shop_crm_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_traffic_sources')->default(0);
        });

        Schema::table('organisation_crm_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_traffic_sources')->default(0);
        });
    }
};
