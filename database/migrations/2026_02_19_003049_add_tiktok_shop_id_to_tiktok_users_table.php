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
        Schema::table('tiktok_users', function (Blueprint $table) {
            $table->string('tiktok_shop_id')->nullable();
            $table->string('tiktok_warehouse_id')->nullable();
            $table->string('tiktok_shop_chiper')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tiktok_users', function (Blueprint $table) {
            $table->dropColumn('tiktok_shop_id');
            $table->dropColumn('tiktok_warehouse_id');
            $table->dropColumn('tiktok_shop_chiper');
        });
    }
};
