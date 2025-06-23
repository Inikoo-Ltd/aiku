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
        Schema::table('group_goods_stats', function (Blueprint $table) {
            $table->unsignedSmallInteger('number_master_collections')->default(0);
            $table->unsignedSmallInteger('number_current_master_collections')->default(0)->comment('status=true');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('group_goods_stats', function (Blueprint $table) {
            $table->dropColumn(['number_master_collections', 'number_current_master_collections']);
        });
    }
};
