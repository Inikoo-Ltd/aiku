<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\PhoneCall;

use App\Enums\CRM\Livechat\ChatPhoneCallStatusEnum;
use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatPhoneCall;
use App\Models\SysAdmin\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StartChatPhoneCall
{
    use AsAction;
    use WithChatPhoneCall;

    public function handle(User $user, ChatAgent $agent, ?Shop $shop = null): ChatPhoneCall
    {
        // Somebody with two tabs open presses the button twice. The call already running is the
        // right answer to both, and the timer they see stays the one that started first.
        $running = $this->activeCallFor($agent);

        if ($running) {
            return $running;
        }

        try {
            return ChatPhoneCall::create([
                'group_id'        => $user->group_id,
                'organisation_id' => $shop?->organisation_id,
                'shop_id'         => $shop?->id,
                'chat_agent_id'   => $agent->id,
                'user_id'         => $user->id,
                'status'          => ChatPhoneCallStatusEnum::IN_PROGRESS,
                'started_at'      => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return $this->activeCallFor($agent);
        }
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user  = $request->user();
        $agent = $this->chatAgentProfileFor($user);
        $shop  = $this->assertShopIsWorkable($user, $request->validated('shop_id'));

        $call = $this->handle($user, $agent, $shop);

        return response()->json($this->callPayload($call, $user));
    }

    public function authorize(ActionRequest $request): bool
    {
        return $this->workableShopIdsFor($request->user()) !== [];
    }

    public function rules(): array
    {
        return [
            'shop_id' => ['sometimes', 'nullable', 'integer'],
        ];
    }
}
