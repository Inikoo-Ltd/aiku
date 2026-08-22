<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 22 Aug 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Json;

use App\Actions\Chat\Staff\MarkStaffConversationRead;
use App\Http\Resources\Chat\StaffMessageResource;
use App\Models\Chat\StaffConversation;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetStaffMessages
{
    use AsAction;

    public function authorize(ActionRequest $request): bool
    {
        return $request->route('staffConversation')->hasParticipant($request->user());
    }

    public function rules(): array
    {
        return ['before_id' => ['sometimes', 'integer']];
    }

    public function asController(StaffConversation $staffConversation, ActionRequest $request): AnonymousResourceCollection
    {
        $messages = $staffConversation->messages()
            ->with(['user', 'translations', 'reactions', 'conversation'])
            ->when($request->validated('before_id'), fn ($query, $beforeId) => $query->where('id', '<', $beforeId))
            ->latest('id')
            ->limit(50)
            ->get()
            ->reverse()
            ->values();

        MarkStaffConversationRead::run($staffConversation, $request->user());

        return StaffMessageResource::collection($messages);
    }
}
