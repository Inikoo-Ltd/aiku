<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 May 2024 12:17:53 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Stubs\Migrations;

use App\Enums\Production\ManufactureTask\ManufactureTaskOperativeRewardAllowanceTypeEnum;
use App\Enums\Production\ManufactureTask\ManufactureTaskOperativeRewardTermsEnum;
use App\Enums\Production\Production\ProductionStateEnum;
use App\Enums\Production\RawMaterial\RawMaterialStateEnum;
use App\Enums\Production\RawMaterial\RawMaterialStockStatusEnum;
use App\Enums\Production\RawMaterial\RawMaterialTypeEnum;
use App\Enums\Production\RawMaterial\RawMaterialUnitEnum;
use Illuminate\Database\Schema\Blueprint;

trait HasManufactureStats
{
    public function productionsStats(Blueprint $table): Blueprint
    {
        $table->unsignedSmallInteger('number_productions')->default(0);
        foreach (ProductionStateEnum::cases() as $case) {
            $table->unsignedInteger('number_productions_state_'.$case->snake())->default(0);
        }

        return $table;
    }

    public function rawMaterialStats(Blueprint $table): Blueprint
    {
        $table->unsignedSmallInteger('number_raw_materials')->default(0);
        foreach (RawMaterialTypeEnum::cases() as $case) {
            $table->unsignedInteger('number_raw_materials_type_'.$case->snake())->default(0);
        }
        foreach (RawMaterialStateEnum::cases() as $case) {
            $table->unsignedInteger('number_raw_materials_state_'.$case->snake())->default(0);
        }
        foreach (RawMaterialUnitEnum::cases() as $case) {
            $table->unsignedInteger('number_raw_materials_unit_'.$case->snake())->default(0);
        }
        foreach (RawMaterialStockStatusEnum::cases() as $case) {
            $table->unsignedInteger('number_raw_materials_stock_status_'.$case->snake())->default(0);
        }

        return $table;
    }

    public function manufactureTaskStats(Blueprint $table): Blueprint
    {
        $table->unsignedSmallInteger('number_manufacture_tasks')->default(0);
        foreach (ManufactureTaskOperativeRewardTermsEnum::cases() as $case) {
            $table->unsignedInteger('number_manufacture_tasks_operative_reward_terms_'.$case->snake())->default(0);
        }
        foreach (ManufactureTaskOperativeRewardAllowanceTypeEnum::cases() as $case) {
            $table->unsignedInteger('number_manufacture_tasks_operative_reward_allowance_type_'.$case->snake())->default(0);
        }

        return $table;
    }

    public function artefactsStats(Blueprint $table): Blueprint
    {
        $table->unsignedSmallInteger('number_artefacts')->default(0);

        return $table;
    }

    public function jobOrdersStats(Blueprint $table): Blueprint
    {
        $table->unsignedSmallInteger('number_job_orders')->default(0);

        return $table;
    }
}
