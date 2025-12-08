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
        Schema::table('portfolios', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->renameColumn('product_id', 'item_id');
        });

        Schema::table('portfolios', function (Blueprint $table) {
            $table->unsignedInteger('item_id')->nullable()->change();
            $table->string('item_type')->nullable()->after('item_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('portfolios', function (Blueprint $table) {
            $table->renameColumn('item_id', 'product_id');
            $table->dropColumn('item_type');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });
    }
};
