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
        Schema::table('group_comms_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_outboxes_type_new_contacter')->default(0);
        });
        Schema::table('organisation_comms_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_outboxes_type_new_contacter')->default(0);
        });
        Schema::table('shop_comms_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_outboxes_type_new_contacter')->default(0);
        });
        Schema::table('org_post_room_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_outboxes_type_new_contacter')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('group_comms_stats', function (Blueprint $table) {
            $table->dropColumn('number_outboxes_type_new_contacter');
        });
        Schema::table('organisation_comms_stats', function (Blueprint $table) {
            $table->dropColumn('number_outboxes_type_new_contacter');
        });
        Schema::table('shop_comms_stats', function (Blueprint $table) {
            $table->dropColumn('number_outboxes_type_new_contacter');
        });
        Schema::table('org_post_room_stats', function (Blueprint $table) {
            $table->dropColumn('number_outboxes_type_new_contacter');
        });
    }
};
