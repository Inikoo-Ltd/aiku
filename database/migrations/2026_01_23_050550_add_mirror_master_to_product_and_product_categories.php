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
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('follow_master_name')->default(false);
            $table->boolean('follow_master_description_title')->default(false);
            $table->boolean('follow_master_description')->default(false);
            $table->boolean('follow_master_description_extra')->default(false);
        });

        Schema::table('product_categories', function (Blueprint $table) {
            $table->boolean('follow_master_name')->default(false);
            $table->boolean('follow_master_description_title')->default(false);
            $table->boolean('follow_master_description')->default(false);
            $table->boolean('follow_master_description_extra')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'follow_master_name',
                'follow_master_description_title',
                'follow_master_description',
                'follow_master_description_extra',
            ]);
        });

        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn([
                'follow_master_name',
                'follow_master_description_title',
                'follow_master_description',
                'follow_master_description_extra',
            ]);
        });
    }
};
