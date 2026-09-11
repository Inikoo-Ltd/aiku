<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 11 Sep 2026 10:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureTask;

use App\Enums\Production\ManufactureTask\ManufactureTaskOperativeRewardAllowanceTypeEnum;
use App\Enums\Production\ManufactureTask\ManufactureTaskOperativeRewardTermsEnum;
use App\Models\Production\ManufactureTask;
use App\Models\Production\Production;
use Lorisleiva\Actions\Concerns\AsAction;

class GetDefaultManufactureTask
{
    use AsAction;

    public const string CODE = 'PROD';

    public function handle(Production $production): ManufactureTask
    {
        $existing = ManufactureTask::where('production_id', $production->id)->whereIn('code', [self::CODE, self::CODE.'-'.$production->code])->first();
        if ($existing) {
            return $existing;
        }

        $codeTakenInOrganisation = ManufactureTask::where('organisation_id', $production->organisation_id)->where('code', self::CODE)->exists();

        return StoreManufactureTask::make()->action($production, [
                'code'                            => $codeTakenInOrganisation ? self::CODE.'-'.$production->code : self::CODE,
                'name'                            => 'Production',
                'task_materials_cost'             => 0,
                'task_energy_cost'                => 0,
                'task_other_cost'                 => 0,
                'task_work_cost'                  => 0,
                'task_lower_target'               => 0,
                'task_upper_target'               => 0,
                'operative_reward_terms'          => ManufactureTaskOperativeRewardTermsEnum::NEVER,
                'operative_reward_allowance_type' => ManufactureTaskOperativeRewardAllowanceTypeEnum::ON_TOP_SALARY,
                'operative_reward_amount'         => 0,
            ]);
    }
}
