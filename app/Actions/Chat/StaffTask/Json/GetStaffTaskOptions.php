<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 16 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\StaffTask\Json;

use App\Enums\Chat\StaffTaskStatusEnum;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Models\Chat\StaffTask;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetStaffTaskOptions
{
    use AsAction;

    public function asController(ActionRequest $request): array
    {
        return [
            'departments'    => StaffTask::departments($request->user()->group_id),
            'my_departments' => StaffTask::departmentsOf($request->user()),
            'priorities'     => collect(ChatPriorityEnum::labels())->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all(),
            'statuses'       => collect(StaffTaskStatusEnum::labels())->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all(),
        ];
    }
}
