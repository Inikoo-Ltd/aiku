<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 Mar 2023 23:04:55 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration

{
    public function up()
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('code');
            $table->string('name');


            $table->jsonb('data');
            $table->jsonb('source');

            $table->unsignedSmallInteger('country_id');
            $table->foreign('country_id')->references('id')->on('central.countries');
            $table->unsignedSmallInteger('language_id');
            $table->foreign('language_id')->references('id')->on('central.languages');
            $table->unsignedSmallInteger('timezone_id');
            $table->foreign('timezone_id')->references('id')->on('central.timezones');
            $table->unsignedSmallInteger('currency_id')->comment('tenant accounting currency');
            $table->foreign('currency_id')->references('id')->on('central.currencies');
            $table->timestampsTz();
            $table->softDeletesTz();
        });

    }


    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
