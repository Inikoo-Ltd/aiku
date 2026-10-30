<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 29 Oct 2026 14:12:55 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\SavedReply;

use App\Models\Chat\ChatSavedReply;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteChatSavedReply
{
    use AsAction;
    use WithChatSavedReplyAuthorisation;

    public function handle(ChatSavedReply $savedReply): void
    {
        $savedReply->delete();
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(?string $organisation, ChatSavedReply $chatSavedReply, Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user instanceof User || !$this->userMayManageSavedReply($user, $chatSavedReply)) {
            return response()->json(['message' => __('You may not delete this saved reply')], 403);
        }

        $this->handle($chatSavedReply);

        return response()->json(['success' => true]);
    }
}
