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
        Schema::table('webpage_stats', function (Blueprint $table) {
            $table->unsignedSmallInteger('number_child_webpages_sub_type_sub_department')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('webpage_stats', function (Blueprint $table) {
            $table->dropColumn('number_child_webpages_sub_type_sub_department');
        });
    }
};
