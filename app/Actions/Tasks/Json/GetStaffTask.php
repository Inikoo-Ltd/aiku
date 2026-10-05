<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 02 Oct 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Tasks\Json;

use App\Http\Resources\Tasks\StaffTaskResource;
use App\Models\Tasks\StaffTask;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetStaffTask
{
    use AsAction;

    public function authorize(ActionRequest $request): bool
    {
        return $request->route('staffTask')->isVisibleTo($request->user());
    }

    public function asController(StaffTask $staffTask): StaffTaskResource
    {
        return new StaffTaskResource($staffTask->load(['requester', 'assignee', 'collaborators.image', 'conversation.participants', 'model', 'media', 'project', 'milestone']));
    }
}
