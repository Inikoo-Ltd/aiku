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
        Schema::table('woo_commerce_users', function (Blueprint $table) {
            $table->string('consumer_key')->nullable()->index();
            $table->string('consumer_secret')->nullable()->index();
            $table->string('store_url')->nullable()->index();
            $table->jsonb('error_response')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('woo_commerce_users', function (Blueprint $table) {
            $table->dropColumn('consumer_key');
            $table->dropColumn('consumer_secret');
            $table->dropColumn('store_url');
            $table->dropColumn('error_response');
        });
    }
};
