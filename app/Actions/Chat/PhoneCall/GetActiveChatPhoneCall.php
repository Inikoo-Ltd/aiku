<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\PhoneCall;

use App\Models\Chat\ChatAgent;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetActiveChatPhoneCall
{
    use AsAction;
    use WithChatPhoneCall;

    /**
     * What the browser asks for once per page load, so a reload puts the timer back where it was
     * rather than losing a call somebody is still on.
     */
    public function asController(ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($this->workableShopIdsFor($user) === []) {
            return response()->json(['call' => null, 'shops' => []]);
        }

        $agent = ChatAgent::where('user_id', $user->id)->first();

        return response()->json($this->callPayload(
            $agent ? $this->activeCallFor($agent) : null,
            $user
        ));
    }
}
