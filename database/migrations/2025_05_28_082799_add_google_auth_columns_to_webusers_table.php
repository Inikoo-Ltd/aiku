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
        Schema::table('web_users', function (Blueprint $table) {
            $table->string('google_id')->nullable();
            $table->string('google_avatar')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('web_users', function (Blueprint $table) {
            $table->dropColumn(['google_id', 'google_avatar']);
        });
    }
};
