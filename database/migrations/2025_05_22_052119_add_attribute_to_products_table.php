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
            $table->boolean('is_vegan')->nullable()->index();
            $table->boolean('is_handmade')->nullable()->index();
            $table->boolean('is_plastic_free')->nullable()->index();
            $table->boolean('is_cruelty_free')->nullable()->index();
            $table->boolean('is_fair_trade')->nullable()->index();
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
            $table->dropColumn('is_vegan');
            $table->dropColumn('is_handmade');
            $table->dropColumn('is_plastic_free');
            $table->dropColumn('is_cruelty_free');
            $table->dropColumn('is_fair_trade');
        });
    }
};
