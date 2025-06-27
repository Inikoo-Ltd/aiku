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
        Schema::table('magento_users', function (Blueprint $table) {
            $table->string('username', 255)->index();
            $table->string('password', 255);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('magento_users', function (Blueprint $table) {
            $table->dropColumn('username');
            $table->dropColumn('password');
        });
    }
};
