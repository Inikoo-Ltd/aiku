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
        Schema::table('master_product_categories', function (Blueprint $table) {
            $table->jsonb('faq')->default('{}');
        });

        Schema::table('product_categories', function (Blueprint $table) {
            $table->jsonb('faq')->default('{}');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('master_product_categories', function (Blueprint $table) {
            $table->dropColumn([
                'faq'
            ]);
        });

        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn([
                'faq'
            ]);
        });
    }
};
