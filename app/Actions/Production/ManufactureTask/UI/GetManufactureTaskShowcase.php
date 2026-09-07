<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 10 May 2024 17:29:22 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureTask\UI;

use App\Models\Production\ManufactureTask;
use Lorisleiva\Actions\Concerns\AsObject;

class GetManufactureTaskShowcase
{
    use AsObject;

    public function handle(ManufactureTask $manufactureTask): array
    {
        return [
            'slug'                             => $manufactureTask->slug,
            'code'                             => $manufactureTask->code,
            'name'                             => $manufactureTask->name,
            'task_work_cost'                   => $manufactureTask->task_work_cost,
            'task_materials_cost'               => $manufactureTask->task_materials_cost,
            'task_energy_cost'                 => $manufactureTask->task_energy_cost,
            'task_other_cost'                  => $manufactureTask->task_other_cost,
            'task_lower_target'                => $manufactureTask->task_lower_target,
            'task_upper_target'                => $manufactureTask->task_upper_target,
            'operative_reward_terms'           => $manufactureTask->operative_reward_terms,
            'operative_reward_allowance_type'  => $manufactureTask->operative_reward_allowance_type,
            'operative_reward_amount'          => $manufactureTask->operative_reward_amount,
        ];
    }
}
