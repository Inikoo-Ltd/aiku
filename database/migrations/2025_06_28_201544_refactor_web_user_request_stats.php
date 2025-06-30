<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Jun 2025 21:16:35 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {

        Schema::table('web_user_requests', function (Blueprint $table) {
            $table->unsignedSmallInteger('organisation_id')->index()->nullable();
            $table->foreign('organisation_id')->references('id')->on('organisations')->nullOnDelete();
            $table->unsignedInteger('webpage_id')->index()->nullable();
            $table->foreign('webpage_id')->references('id')->on('webpages')->nullOnDelete();
        });

        Schema::table('group_web_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_web_user_requests')->default(0);
        });

        Schema::table('organisation_web_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_web_user_requests')->default(0);
        });

        Schema::table('website_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_web_user_requests')->default(0);
        });

        Schema::table('webpage_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_web_user_requests')->default(0);
        });

        Schema::table('web_user_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_web_user_requests')->default(0);
        });

    }


    public function down(): void
    {
        Schema::table('web_user_requests', function (Blueprint $table) {
            $table->dropForeign(['organisation_id']);
            $table->dropColumn('organisation_id');
            $table->dropForeign(['webpage_id']);
            $table->dropColumn('webpage_id');
        });

        Schema::table('group_web_stats', function (Blueprint $table) {
            $table->dropColumn('number_web_user_requests');
        });

        Schema::table('organisation_web_stats', function (Blueprint $table) {
            $table->dropColumn('number_web_user_requests');
        });

        Schema::table('website_stats', function (Blueprint $table) {
            $table->dropColumn('number_web_user_requests');
        });

        Schema::table('webpage_stats', function (Blueprint $table) {
            $table->dropColumn('number_web_user_requests');
        });

        Schema::table('web_user_stats', function (Blueprint $table) {
            $table->dropColumn('number_web_user_requests');
        });
    }
};
