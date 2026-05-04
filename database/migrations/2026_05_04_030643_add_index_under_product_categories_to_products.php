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
            $table->unsignedInteger('index_under_master_sub_department')->nullable()->index();
            $table->unsignedInteger('index_under_master_department')->nullable()->index();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('index_under_sub_department')->nullable()->index();
            $table->unsignedInteger('index_under_department')->nullable()->index();
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
            $table->dropColumn([
                'index_under_master_sub_department',
                'index_under_master_department',
            ]);
        });
        
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'index_under_sub_department',
                'index_under_department',
            ]);
        });
    }
};
