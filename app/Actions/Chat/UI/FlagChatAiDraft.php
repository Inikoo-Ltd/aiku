<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 04:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\UI;

use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Enums\CRM\Livechat\ChatAiDraftStatusEnum;
use App\Models\Chat\ChatAiDraft;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Staff saying an answer sent without a person was wrong, and why: the reason is what the
 * answers are corrected from. It stays recorded as sent, and the flag closes automatic sending
 * for that topic in that shop until it has aged out of the window.
 */
class FlagChatAiDraft
{
    use AsAction;
    use WithChatAgentAuthorisation;

    public function handle(ChatAiDraft $chatAiDraft, int $userId, string $reason): ChatAiDraft
    {
        if ($chatAiDraft->status === ChatAiDraftStatusEnum::AUTO_SENT && !$chatAiDraft->flagged_wrong_at) {
            $chatAiDraft->update([
                'flagged_wrong_at'   => now(),
                'flagged_by_user_id' => $userId,
                'flagged_reason'     => $reason,
            ]);
        }

        return $chatAiDraft;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function asController(ChatAiDraft $chatAiDraft, ActionRequest $request): ChatAiDraft
    {
        abort_unless(self::mayFlag($request->user(), $chatAiDraft), 404);
        abort_if((bool) $chatAiDraft->flagged_wrong_at, 422, __('This reply is already marked as wrong'));

        return $this->handle($chatAiDraft, $request->user()->id, $request->validated('reason'));
    }

    public static function mayFlag(User $user, ChatAiDraft $chatAiDraft): bool
    {
        $session = $chatAiDraft->session();

        return $chatAiDraft->group_id === $user->group_id
            && ($user->hasGroupAccess() || ($session?->shop && (new self())->userCanActOnChatOnShop($user, $session->shop)));
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }

    public function jsonResponse(): JsonResponse
    {
        return response()->json(['success' => true]);
    }
}
