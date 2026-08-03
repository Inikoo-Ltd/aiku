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
        Schema::table('website_stats', function (Blueprint $table) {
            $table->integer('number_announcements')->default(0);
            $table->integer('number_active_announcements')->default(0);
            $table->integer('number_inactive_announcements')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('website_stats', function (Blueprint $table) {
            $table->dropColumn([
                'number_announcements',
                'number_active_announcements',
                'number_inactive_announcements',
            ]);
        });
    }
};
