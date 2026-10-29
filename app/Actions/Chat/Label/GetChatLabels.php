<?php

/*
 * Author: Yudhistira <artha@aw-advantage.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Chat\Label;

use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Models\Chat\ChatLabel;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetChatLabels
{
    use AsAction;
    use WithChatAgentAuthorisation;

    public function authorize(ActionRequest $request): bool
    {
        return $this->userCanViewChatOnOrganisation($request->user(), $request->route('organisation'));
    }

    /**
     * @return Collection<int, ChatLabel>
     */
    public function handle(Organisation $organisation): Collection
    {
        return ChatLabel::where('organisation_id', $organisation->id)->orderBy('name')->get(['id', 'name', 'colour']);
    }

    public function asController(Organisation $organisation): Collection
    {
        return $this->handle($organisation);
    }

    public function jsonResponse(Collection $labels): JsonResponse
    {
        return response()->json(['data' => $labels]);
    }
}
