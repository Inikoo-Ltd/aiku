<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 17:53:02 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Enums\Helpers\TaxNumber\TaxNumberStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('tax_numbers', function (Blueprint $table) {
            $table->id();
            $table->string('owner_type');
            $table->unsignedInteger('owner_id');
            $table->string('country_code', 2)->nullable()->index();
            $table->string('number');
            $table->string('type')->nullable()->index();
            $table->unsignedSmallInteger('country_id')->nullable()->index();
            $table->foreign('country_id')->references('id')->on('countries');
            $table->string('status')->index()->default(TaxNumberStatusEnum::UNKNOWN->value);
            $table->boolean('valid')->default(false);
            $table->jsonb('data');
            $table->boolean('historic')->index()->default(false);
            $table->unsignedSmallInteger('usage')->default(0);
            $table->string('checksum')->index()->nullable()->comment('hash of country_code,number,status');

            $table->timestampsTz();
            $table->dateTimeTz('checked_at')->nullable()->comment('Last time was validated online');
            $table->dateTimeTz('invalid_checked_at')->nullable()->comment('Last time was validated online with tax number invalid');

            $table->dateTimeTz('external_service_failed_at')->nullable()->comment('Last time on;ine validation fail due external service down');
            $table->softDeletesTz();
            $table->index(['owner_type', 'owner_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('tax_numbers');
    }
};
