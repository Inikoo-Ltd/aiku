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

class UpdateChatSavedReply
{
    use AsAction;
    use WithChatSavedReplyAuthorisation;

    /**
     * @param  array{title?: string, body?: string}  $modelData
     */
    public function handle(ChatSavedReply $savedReply, array $modelData): ChatSavedReply
    {
        $savedReply->update($modelData);

        return $savedReply;
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(?string $organisation, ChatSavedReply $chatSavedReply, Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user instanceof User || !$this->userMayManageSavedReply($user, $chatSavedReply)) {
            return response()->json(['message' => __('You may not change this saved reply')], 403);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'body'  => ['sometimes', 'required', 'string', 'max:20000'],
        ]);

        return response()->json(['data' => $this->savedReplyResource($this->handle($chatSavedReply, $validated), $user)]);
    }
}
