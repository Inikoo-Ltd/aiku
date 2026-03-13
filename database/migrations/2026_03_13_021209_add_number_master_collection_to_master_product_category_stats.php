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
        Schema::table('master_product_category_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_master_collections')->default(0);
            $table->unsignedInteger('number_current_master_collections')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('master_product_category_stats', function (Blueprint $table) {
            $table->dropColumn([
                'number_master_collections',
                'number_current_master_collections'
            ]);
        });
    }
};
