<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 08 May 2024 10:34:52 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Production\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Production\ManufactureTask\ManufactureTaskOperativeRewardAllowanceTypeEnum;
use App\Enums\Production\ManufactureTask\ManufactureTaskOperativeRewardTermsEnum;
use App\Models\Production\ManufactureTask;
use App\Models\Production\Production;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ProductionHydrateManufactureTasks implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Production $production): string
    {
        return $production->id;
    }

    public function handle(Production $production): void
    {
        $stats = [
            'number_manufacture_tasks' => $production->manufactureTasks()->count()
        ];



        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'manufacture_tasks',
                field: 'operative_reward_terms',
                enum: ManufactureTaskOperativeRewardTermsEnum::class,
                models: ManufactureTask::class,
                where: function ($q) use ($production) {
                    $q->where('production_id', $production->id);
                }
            )
        );

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'manufacture_tasks',
                field: 'operative_reward_allowance_type',
                enum: ManufactureTaskOperativeRewardAllowanceTypeEnum::class,
                models: ManufactureTask::class,
                where: function ($q) use ($production) {
                    $q->where('production_id', $production->id);
                }
            )
        );


        $production->stats()->update($stats);
    }
}
