<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\PhoneCall;

use App\Enums\CRM\Livechat\ChatPhoneCallStatusEnum;
use App\Models\Chat\ChatPhoneCall;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class CancelChatPhoneCall
{
    use AsAction;
    use WithChatPhoneCall;

    public function handle(ChatPhoneCall $call): ChatPhoneCall
    {
        return $this->closeCall($call, ChatPhoneCallStatusEnum::CANCELLED);
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user  = $request->user();
        $agent = $this->chatAgentProfileFor($user);
        $call  = $this->activeCallFor($agent);

        if ($call) {
            $this->handle($call);
        }

        return response()->json($this->callPayload(null, $user));
    }

    public function authorize(ActionRequest $request): bool
    {
        return $this->workableShopIdsFor($request->user()) !== [];
    }
}
