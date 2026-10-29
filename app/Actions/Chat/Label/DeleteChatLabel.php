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
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Taking a label away takes it off every conversation that has it, so only somebody who
 * supervises the organisation's chat may do it.
 */
class DeleteChatLabel
{
    use AsAction;
    use WithChatAgentAuthorisation;

    public function authorize(ActionRequest $request): bool
    {
        $organisation = $request->route('organisation');
        $label        = $request->route('chatLabel');

        return $organisation instanceof Organisation
            && $label instanceof ChatLabel
            && $label->organisation_id === $organisation->id
            && $this->userSupervisesChatOnOrganisation($request->user(), $organisation);
    }

    public function handle(ChatLabel $label): void
    {
        $label->delete();
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(Organisation $organisation, ChatLabel $chatLabel): void
    {
        $this->handle($chatLabel);
    }

    public function jsonResponse(): JsonResponse
    {
        return response()->json(['success' => true]);
    }
}
