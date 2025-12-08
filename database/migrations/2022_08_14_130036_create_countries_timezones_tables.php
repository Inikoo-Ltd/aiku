<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Sun, 14 Aug 2022 21:00:50 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Inikoo
 *  Version 4.0
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('country_language', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('country_id')->index();
            $table->unsignedSmallInteger('language_id')->index();
            $table->unsignedSmallInteger('priority')->default(1)->index();
            $table->string('status')->nullable()->index();
            $table->timestampsTz();
            $table->unique(['country_id', 'language_id']);
            $table->foreign('country_id')->references('id')->on('countries')->onDelete('cascade');
            $table->foreign('language_id')->references('id')->on('languages')->onDelete('cascade');
        });

        Schema::create('country_timezone', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('country_id')->index();
            $table->unsignedSmallInteger('timezone_id')->index();
            $table->unsignedSmallInteger('priority')->default(1)->index();
            $table->string('type')->nullable()->index();
            $table->timestampsTz();
            $table->unique(['country_id', 'timezone_id']);
            $table->foreign('country_id')->references('id')->on('countries')->onDelete('cascade');
            $table->foreign('timezone_id')->references('id')->on('timezones')->onDelete('cascade');
        });

        Schema::table('countries', function (Blueprint $table) {
            $table->foreign('timezone_id')->references('id')->on('timezones');
        });
    }

    public function down(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->dropColumn('timezone_id');
        });
        Schema::dropIfExists('country_timezone');
        Schema::dropIfExists('country_language');
    }
};
