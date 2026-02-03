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
        Schema::table('shop_comms_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_email_templates')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('shop_comms_stats', function (Blueprint $table) {
            $table->dropColumn('number_email_templates');
        });
    }
};
