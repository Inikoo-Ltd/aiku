<?php

/*
 * Author Louis Perez
 * Created on 13-11-2026-15h-47m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Chat;

use App\Actions\Chat\Staff\SendStaffMessage;
use App\Models\Chat\StaffConversation;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class SendMobileStaffMessage
{
    use AsController;
    use WithMobileStaffChat;

    public function asController(StaffConversation $staffConversation, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($staffConversation->canBeWrittenToBy($user), 403);

        $data = Validator::make($request->all(), [
            'body' => ['required', 'string', 'max:5000'],
        ])->validate();

        $message = SendStaffMessage::make()->handle($staffConversation, $user, ['body' => $data['body']]);

        return response()->json(['data' => $this->messagePayload($message->load('user'), $user)]);
    }
}
