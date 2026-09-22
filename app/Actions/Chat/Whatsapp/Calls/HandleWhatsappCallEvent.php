<?php

/*
 * Author: Eka Yudinata <ekayudinata@gmail.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Whatsapp\Calls;

use App\Actions\Chat\MetaChatSession\ReopenMetaChatSession;
use App\Actions\Chat\MetaChatSession\StoreMetaChatSession;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Enums\CRM\Livechat\MetaChatCallDirectionEnum;
use App\Enums\CRM\Livechat\MetaChatCallStatusEnum;
use App\Events\BroadcastWhatsappCallEvent;
use App\Models\CRM\Customer;
use App\Models\Catalogue\Shop;
use App\Models\Chat\MetaChannel;
use App\Models\Chat\MetaChatCall;
use App\Models\Chat\MetaChatSession;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class HandleWhatsappCallEvent
{
    use AsAction;

    public string $jobQueue = 'urgent';

    /**
     * @param  array<string, mixed>  $value  The `changes[].value` node of a `calls` webhook
     */
    public function asJob(array $value): void
    {
        $this->handle($value);
    }

    /**
     * @param  array<string, mixed>  $value
     */
    public function handle(array $value): void
    {
        $phoneNumberId = (string) Arr::get($value, 'metadata.phone_number_id');

        $shop = Shop::whereJsonContains('settings->whatsapp->phone_number_id', $phoneNumberId)->first();

        if (!$shop) {
            Log::warning('WhatsApp call for unknown phone_number_id', [
                'phone_number_id' => $phoneNumberId,
            ]);

            return;
        }

        $metaChannel = MetaChannel::where('code', 'whatsapp')->first();

        if (!$metaChannel) {
            Log::warning('WhatsApp meta channel is not configured');

            return;
        }

        foreach (Arr::get($value, 'calls', []) as $call) {
            match ((string) Arr::get($call, 'event')) {
                'connect'   => $this->connect($shop, $metaChannel, $value, $call),
                'terminate' => $this->terminate($call),
                default     => null,
            };
        }
    }

    /**
     * A connect for a call the business placed carries the customer's answer, so the call is
     * already up; a connect from the customer is the phone ringing and nothing is answered
     * until an agent picks it up.
     *
     * @param  array<string, mixed>  $value
     * @param  array<string, mixed>  $call
     */
    protected function connect(Shop $shop, MetaChannel $metaChannel, array $value, array $call): void
    {
        $waCallId = (string) Arr::get($call, 'id');

        if ($waCallId === '') {
            return;
        }

        $direction = MetaChatCallDirectionEnum::fromMeta(Arr::get($call, 'direction'));

        // The business end of an outgoing call was written when we dialled, and this webhook
        // only brings the customer's SDP answer back to it.
        if ($direction === MetaChatCallDirectionEnum::BUSINESS_INITIATED) {
            $metaChatCall = MetaChatCall::where('wa_call_id', $waCallId)->first();

            if (!$metaChatCall) {
                return;
            }

            $metaChatCall->update([
                'status'      => MetaChatCallStatusEnum::IN_PROGRESS,
                'answered_at' => $metaChatCall->answered_at ?? now(),
                'metadata'    => array_merge($metaChatCall->metadata ?? [], [
                    'remote_sdp' => Arr::get($call, 'session.sdp'),
                ]),
            ]);

            BroadcastWhatsappCallEvent::dispatch($metaChatCall->fresh());

            return;
        }

        if (MetaChatCall::where('wa_call_id', $waCallId)->exists()) {
            return;
        }

        $digits          = preg_replace('/\D/', '', (string) Arr::get($call, 'from'));
        $profileName     = Arr::get($value, 'contacts.0.profile.name');
        $metaChatSession = $this->resolveSession($shop, $metaChannel, $digits, $profileName);

        $metaChatCall = MetaChatCall::create([
            'meta_channel_id'      => $metaChannel->id,
            'meta_chat_session_id' => $metaChatSession->id,
            'shop_id'              => $shop->id,
            'customer_id'          => $metaChatSession->customer_id,
            'wa_call_id'           => $waCallId,
            'direction'            => $direction,
            'status'               => MetaChatCallStatusEnum::RINGING,
            'phone_number'         => '+'.$digits,
            'ringing_at'           => now(),
            'metadata'             => [
                'profile_name' => $profileName,
                // The offer is what an agent's browser answers, and it is only good for the
                // seconds Meta holds the call open, so it is kept with the call rather than
                // fetched again later.
                'remote_sdp'   => Arr::get($call, 'session.sdp'),
            ],
        ]);

        BroadcastWhatsappCallEvent::dispatch($metaChatCall);
    }

    /**
     * @param  array<string, mixed>  $call
     */
    protected function terminate(array $call): void
    {
        $metaChatCall = MetaChatCall::where('wa_call_id', (string) Arr::get($call, 'id'))->first();

        if (!$metaChatCall || !$metaChatCall->isLive()) {
            return;
        }

        $duration = Arr::get($call, 'duration');

        $metaChatCall->update([
            'status'             => $this->terminalStatus($metaChatCall, $call),
            'ended_at'           => now(),
            'duration_seconds'   => $duration !== null ? (int) $duration : $metaChatCall->elapsedSeconds(),
            'termination_reason' => Arr::get($call, 'status'),
        ]);

        BroadcastWhatsappCallEvent::dispatch($metaChatCall->fresh());
    }

    /**
     * Meta reports only COMPLETED or FAILED, which does not separate a call nobody picked up
     * from one that broke. A call that never reached an agent is a missed call, and that is
     * the distinction the inbox has to show.
     *
     * @param  array<string, mixed>  $call
     */
    protected function terminalStatus(MetaChatCall $metaChatCall, array $call): MetaChatCallStatusEnum
    {
        if ($metaChatCall->status === MetaChatCallStatusEnum::IN_PROGRESS) {
            return MetaChatCallStatusEnum::COMPLETED;
        }

        return strtoupper((string) Arr::get($call, 'status')) === 'COMPLETED'
            ? MetaChatCallStatusEnum::COMPLETED
            : MetaChatCallStatusEnum::MISSED;
    }

    /**
     * A call is part of the conversation the customer already has, so it reuses the session
     * their messages land in and opens one only when they have never written.
     */
    protected function resolveSession(Shop $shop, MetaChannel $metaChannel, string $digits, ?string $profileName): MetaChatSession
    {
        $metaChatSession = MetaChatSession::where('meta_channel_id', $metaChannel->id)
            ->where('shop_id', $shop->id)
            ->whereIn('phone_number', ['+'.$digits, $digits])
            ->latest('id')
            ->first();

        if (!$metaChatSession) {
            return StoreMetaChatSession::run([
                'shop_id'      => $shop->id,
                'customer_id'  => Customer::where('shop_id', $shop->id)
                    ->whereRaw("regexp_replace(coalesce(phone,''), '\D', '', 'g') LIKE ?", ['%'.substr($digits, -9)])
                    ->value('id'),
                'phone_number' => '+'.$digits,
                'name'         => $profileName,
            ]);
        }

        if ($metaChatSession->status === ChatSessionStatusEnum::CLOSED) {
            return ReopenMetaChatSession::make()->reopenToWaiting($metaChatSession);
        }

        return $metaChatSession;
    }
}
