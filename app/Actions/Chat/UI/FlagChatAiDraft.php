<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 04:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\UI;

use App\Enums\CRM\Livechat\ChatAiDraftStatusEnum;
use App\Models\Chat\ChatAiDraft;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Staff saying an answer sent without a person was wrong. It stays recorded as sent, and the
 * flag closes automatic sending for that topic in that shop until it has aged out of the window.
 */
class FlagChatAiDraft
{
    use AsAction;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->hasGroupAccess();
    }

    public function handle(ChatAiDraft $chatAiDraft, int $userId): ChatAiDraft
    {
        if ($chatAiDraft->status === ChatAiDraftStatusEnum::AUTO_SENT && !$chatAiDraft->flagged_wrong_at) {
            $chatAiDraft->update(['flagged_wrong_at' => now(), 'flagged_by_user_id' => $userId]);
        }

        return $chatAiDraft;
    }

    public function asController(ChatAiDraft $chatAiDraft, ActionRequest $request): RedirectResponse
    {
        abort_unless($chatAiDraft->group_id === $request->user()->group_id, 404);

        $this->handle($chatAiDraft, $request->user()->id);

        return back();
    }
}
