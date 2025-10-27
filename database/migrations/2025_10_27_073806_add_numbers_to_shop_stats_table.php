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
        Schema::table('shop_stats', function (Blueprint $table) {
            $table->integer("number_brands")->default(0);
            $table->integer("number_current_brands")->default(0);
            $table->integer("number_tags")->default(0);
            $table->integer("number_current_tags")->default(0);
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
            $table->dropColumn("number_brands");
            $table->dropColumn("number_current_brands");
            $table->dropColumn("number_tags");
            $table->dropColumn("number_current_tags");
        });
    }
};
