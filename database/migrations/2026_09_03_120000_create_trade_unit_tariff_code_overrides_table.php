<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('trade_unit_tariff_code_overrides', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id');
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedSmallInteger('organisation_id');
            $table->foreign('organisation_id')->references('id')->on('organisations');
            $table->unsignedInteger('trade_unit_id');
            $table->foreign('trade_unit_id')->references('id')->on('trade_units');
            $table->string('national_extension', 4)->comment('Digits 7-10 of the tariff code for this organisation; the 6-digit HS heading is always the shared one');
            $table->text('reason');
            $table->unsignedInteger('approved_by_user_id');
            $table->foreign('approved_by_user_id')->references('id')->on('users');
            $table->dateTimeTz('approved_at');
            $table->timestampsTz();
            $table->unique(['organisation_id', 'trade_unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_unit_tariff_code_overrides');
    }
};
