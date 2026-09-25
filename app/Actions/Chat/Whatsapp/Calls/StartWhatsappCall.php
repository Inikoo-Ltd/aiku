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
use App\Models\Chat\MetaChatSession;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Dialling out is the mirror of answering: the agent's browser makes the SDP offer, Meta rings
 * the customer, and the customer's answer comes back on the connect webhook, which is where the
 * call is marked in progress.
 */
class StartWhatsappCall
{
    use AsAction;

    public function handle(MetaChatSession $metaChatSession, User $user, string $sdpOffer): array
    {
        if (MetaChatCall::where('meta_chat_session_id', $metaChatSession->id)->live()->exists()) {
            return ['ok' => false, 'message' => __('There is already a call in this conversation.'), 'code' => 409];
        }

        $digits = preg_replace('/\D/', '', (string) $metaChatSession->phone_number);

        $dialled = SendWhatsappCallAction::run($metaChatSession->shop, [
            'to'      => $digits,
            'action'  => 'connect',
            'session' => ['sdp_type' => 'offer', 'sdp' => $sdpOffer],
        ]);

        $waCallId = (string) Arr::get($dialled, 'body.calls.0.id');

        if (!$dialled['ok'] || $waCallId === '') {
            return [
                'ok'      => false,
                'message' => Arr::get($dialled, 'body.error.error_data.details')
                    ?? Arr::get($dialled, 'body.error.message')
                    ?? __('The call could not be started.'),
                'code'    => 422,
            ];
        }

        $metaChatCall = MetaChatCall::create([
            'meta_channel_id'      => $metaChatSession->meta_channel_id,
            'meta_chat_session_id' => $metaChatSession->id,
            'shop_id'              => $metaChatSession->shop_id,
            'customer_id'          => $metaChatSession->customer_id,
            'user_id'              => $user->id,
            'wa_call_id'           => $waCallId,
            'direction'            => MetaChatCallDirectionEnum::BUSINESS_INITIATED,
            'status'               => MetaChatCallStatusEnum::RINGING,
            'phone_number'         => '+'.$digits,
            'ringing_at'           => now(),
            'metadata'             => [],
        ]);

        BroadcastWhatsappCallEvent::dispatch($metaChatCall);

        return ['ok' => true, 'data' => ['id' => $metaChatCall->id]];
    }

    public function rules(): array
    {
        return [
            'sdp' => ['required', 'string'],
        ];
    }

    public function asController(string $organisation, MetaChatSession $metaChatSession, ActionRequest $request): array
    {
        $user = Auth::user();

        if (!$user || !ChatAgent::where('user_id', $user->id)->exists()) {
            return ['ok' => false, 'message' => __('Only agents can start calls.'), 'code' => 403];
        }

        return $this->handle($metaChatSession, $user, $request->validated()['sdp']);
    }
}
