<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 17 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Tasks\Json;

use App\Http\Resources\Chat\StaffConversationResource;
use App\Models\Tasks\StaffTask;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetStaffTaskConversation
{
    use AsAction;

    public function authorize(ActionRequest $request): bool
    {
        return $request->route('staffTask')->conversation?->canBeAccessedBy($request->user()) ?? false;
    }

    public function asController(StaffTask $staffTask): StaffConversationResource
    {
        return new StaffConversationResource($staffTask->conversation->load(['participants.image', 'context']));
    }
}
