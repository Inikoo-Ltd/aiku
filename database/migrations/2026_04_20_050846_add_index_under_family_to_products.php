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
        Schema::table('master_assets', function (Blueprint $table) {
            $table->unsignedInteger('index_under_master_family')->nullable()->index();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('index_under_family')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('master_assets', function (Blueprint $table) {
            $table->dropIndex(['index_under_master_family']);
            $table->dropColumn(['index_under_master_family']);
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['index_under_family']);
            $table->dropColumn(['index_under_family']);
        });
    }
};
