<?php

/*
 * Author: Eka Yudinata <ekayudinata@gmail.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Whatsapp\Calls;

use App\Enums\CRM\Livechat\MetaChatCallStatusEnum;
use App\Events\BroadcastWhatsappCallEvent;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\MetaChatCall;
use Illuminate\Support\Facades\Auth;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Refusing a call that is ringing and hanging up on one in progress are the same request to
 * Meta with a different verb, and which of the two applies is decided by the state the call
 * is already in rather than by the caller, so a stale button cannot reject a live call.
 */
class EndWhatsappCall
{
    use AsAction;

    public function handle(MetaChatCall $metaChatCall): array
    {
        if (!$metaChatCall->isLive()) {
            return ['ok' => false, 'message' => __('This call has already ended.'), 'code' => 409];
        }

        $wasRinging = $metaChatCall->status === MetaChatCallStatusEnum::RINGING;

        SendWhatsappCallAction::run($metaChatCall->shop, [
            'call_id' => $metaChatCall->wa_call_id,
            'action'  => $wasRinging ? 'reject' : 'terminate',
        ]);

        // Meta sends a terminate webhook for both, but the agent's console should not wait on
        // it, and a webhook that never arrives would otherwise leave the call ringing for ever.
        $metaChatCall->update([
            'status'             => $wasRinging ? MetaChatCallStatusEnum::REJECTED : MetaChatCallStatusEnum::COMPLETED,
            'ended_at'           => now(),
            'duration_seconds'   => $metaChatCall->elapsedSeconds(),
            'termination_reason' => $wasRinging ? 'rejected_by_agent' : 'ended_by_agent',
        ]);

        BroadcastWhatsappCallEvent::dispatch($metaChatCall->fresh());

        return ['ok' => true];
    }

    public function asController(string $organisation, MetaChatCall $metaChatCall, ActionRequest $request): array
    {
        $user = Auth::user();

        if (!$user || !ChatAgent::where('user_id', $user->id)->exists()) {
            return ['ok' => false, 'message' => __('Only agents can end calls.'), 'code' => 403];
        }

        return $this->handle($metaChatCall);
    }
}
