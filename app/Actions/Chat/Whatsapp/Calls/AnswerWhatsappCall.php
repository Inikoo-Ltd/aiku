<?php

/*
 * Author: Eka Yudinata <ekayudinata@gmail.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Whatsapp\Calls;

use App\Enums\CRM\Livechat\MetaChatCallDirectionEnum;
use App\Enums\CRM\Livechat\MetaChatCallStatusEnum;
use App\Events\BroadcastWhatsappCallEvent;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\MetaChatCall;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\Auth;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class AnswerWhatsappCall
{
    use AsAction;

    /**
     * The agent's browser has the microphone, so it makes the SDP answer and this only
     * carries it to Meta. Pre-accept opens the media path before the call is formally
     * accepted, which is what stops the first second of speech being clipped; Meta rejects
     * an accept whose answer differs from the pre-accept, so the same one is sent twice.
     */
    public function handle(MetaChatCall $metaChatCall, User $user, string $sdpAnswer): array
    {
        // Two agents can reach a ringing call at the same moment. The first to claim it wins
        // the row, and the second is told rather than sending a second accept for a call
        // Meta already considers answered.
        $claimed = MetaChatCall::where('id', $metaChatCall->id)
            ->where('status', MetaChatCallStatusEnum::RINGING)
            ->where('direction', MetaChatCallDirectionEnum::USER_INITIATED)
            ->update([
                'status'      => MetaChatCallStatusEnum::IN_PROGRESS,
                'user_id'     => $user->id,
                'answered_at' => now(),
            ]);

        if ($claimed === 0) {
            return ['ok' => false, 'message' => __('This call has already been answered.'), 'code' => 409];
        }

        $metaChatCall->refresh();

        $session = ['sdp_type' => 'answer', 'sdp' => $sdpAnswer];

        SendWhatsappCallAction::run($metaChatCall->shop, [
            'call_id' => $metaChatCall->wa_call_id,
            'action'  => 'pre_accept',
            'session' => $session,
        ]);

        $accepted = SendWhatsappCallAction::run($metaChatCall->shop, [
            'call_id' => $metaChatCall->wa_call_id,
            'action'  => 'accept',
            'session' => $session,
        ]);

        if (!$accepted['ok']) {
            $metaChatCall->update([
                'status'             => MetaChatCallStatusEnum::FAILED,
                'ended_at'           => now(),
                'termination_reason' => 'accept_failed',
            ]);

            BroadcastWhatsappCallEvent::dispatch($metaChatCall->fresh());

            return ['ok' => false, 'message' => __('The call could not be answered.'), 'code' => 422];
        }

        BroadcastWhatsappCallEvent::dispatch($metaChatCall);

        return ['ok' => true, 'data' => ['remote_sdp' => data_get($metaChatCall->metadata, 'remote_sdp')]];
    }

    public function rules(): array
    {
        return [
            'sdp' => ['required', 'string'],
        ];
    }

    public function asController(string $organisation, MetaChatCall $metaChatCall, ActionRequest $request): array
    {
        $user = Auth::user();

        if (!$user || !ChatAgent::where('user_id', $user->id)->exists()) {
            return ['ok' => false, 'message' => __('Only agents can answer calls.'), 'code' => 403];
        }

        return $this->handle($metaChatCall, $user, $request->validated()['sdp']);
    }
}
