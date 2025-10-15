<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 08 Jul 2025 22:52:17 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Stubs\Migrations;

use Illuminate\Database\Schema\Blueprint;

trait HasProductInformation
{
    public function addProductInformationFields(Blueprint $table): void
    {
        $table->text('cpnp_number')->nullable()->index();
        $table->text('scpn_number')->nullable()->index();
        $table->text('ufi_number')->nullable()->index();
        $table->string('country_of_origin')->nullable()->index();
        $table->string('tariff_code')->nullable()->index();
        $table->string('duty_rate')->nullable()->index();
        $table->string('hts_us')->nullable()->index();
        $table->text('marketing_ingredients')->nullable();
    }

    public function getProductInformationFieldNames(): array
    {
        return [
            'cpnp_number',
            'scpn_number',
            'ufi_number',
            'country_of_origin',
            'origin_country_id',
            'tariff_code',
            'duty_rate',
            'hts_us',
        ];
    }
}
