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
        Schema::table('product_categories', function (Blueprint $table) {
            $table->boolean('is_name_reviewed')->nullable();
            $table->boolean('is_title_reviewed')->nullable();
            $table->boolean('is_description_reviewed')->nullable();
            $table->boolean('is_extra_description_reviewed')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn('is_name_reviewed');
            $table->dropColumn('is_title_reviewed');
            $table->dropColumn('is_description_reviewed');
            $table->dropColumn('is_extra_description_reviewed');
        });
    }
};
