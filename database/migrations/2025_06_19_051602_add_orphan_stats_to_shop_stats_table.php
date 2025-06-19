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
        Schema::table('shop_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_families_no_department')->default(0);
            $table->unsignedInteger('number_products_no_family')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('shop_stats', function (Blueprint $table) {
            $table->dropColumn('number_families_no_department');
            $table->dropColumn('number_products_no_family');
        });
    }
};
