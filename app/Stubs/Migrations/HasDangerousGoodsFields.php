<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 07 Jul 2025 18:44:52 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Stubs\Migrations;

use Illuminate\Database\Schema\Blueprint;

trait HasDangerousGoodsFields
{
    /**
     * Add dangerous goods fields to a table
     *
     * @param Blueprint $table The table blueprint
     * @return void
     */
    public function addDangerousGoodsFields(Blueprint $table): void
    {
        $table->string('un_number')->nullable();
        $table->string('un_class')->nullable();
        $table->string('packing_group')->nullable();
        $table->string('proper_shipping_name')->nullable();
        $table->string('hazard_identification_number')->nullable();
        $table->string('gpsr_manufacturer')->nullable();
        $table->string('gpsr_eu_responsible')->nullable();
        $table->string('gpsr_warnings')->nullable();
        $table->string('gpsr_manual')->nullable();
        $table->string('gpsr_class_category_danger')->nullable();
        $table->string('gpsr_class_languages')->nullable();
        $table->boolean('pictogram_toxic')->default(false);
        $table->boolean('pictogram_corrosive')->default(false);
        $table->boolean('pictogram_explosive')->default(false);
        $table->boolean('pictogram_flammable')->default(false);
        $table->boolean('pictogram_gas')->default(false);
        $table->boolean('pictogram_environment')->default(false);
        $table->boolean('pictogram_health')->default(false);
        $table->boolean('pictogram_oxidising')->default(false);
        $table->boolean('pictogram_danger')->default(false);
    }

    /**
     * Get the list of dangerous goods field names
     *
     * @return array The list of field names
     */
    public function getDangerousGoodsFieldNames(): array
    {
        return [
            'un_number',
            'un_class',
            'packing_group',
            'proper_shipping_name',
            'hazard_identification_number',
            'gpsr_manufacturer',
            'gpsr_eu_responsible',
            'gpsr_warnings',
            'gpsr_manual',
            'gpsr_class_category_danger',
            'gpsr_class_languages',
            'pictogram_toxic',
            'pictogram_corrosive',
            'pictogram_explosive',
            'pictogram_flammable',
            'pictogram_gas',
            'pictogram_environment',
            'pictogram_health',
            'pictogram_oxidising',
            'pictogram_danger'
        ];
    }
}
