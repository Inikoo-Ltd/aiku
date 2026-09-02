<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\WhatsappCampaign;

use App\Actions\Chat\MetaChatSession\StoreMetaChatSession;
use App\Enums\Comms\WhatsappDeliveryChannel\WhatsappDeliveryChannelStateEnum;
use App\Models\Comms\WhatsappCampaign;
use App\Models\Comms\WhatsappDeliveryChannel;
use App\Models\Comms\WhatsappRecipient;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * Materialises one chunk of recipients into a delivery channel, then hands the channel to
 * its own send job. Every recipient gets a chat session up front so the messages a campaign
 * sends land in the same thread as the conversation that follows.
 */
class ProcessSendWhatsappCampaign
{
    use AsAction;

    public string $jobQueue = 'urgent';

    /**
     * @param  array<int, array<string, mixed>>  $recipientRows
     */
    public function handle(?int $campaignId, array $recipientRows): void
    {
        if (!$campaignId) {
            return;
        }

        $campaign = WhatsappCampaign::find($campaignId);

        if (!$campaign || !$recipientRows) {
            return;
        }

        $channel = WhatsappDeliveryChannel::create([
            'whatsapp_campaign_id' => $campaign->id,
            'number_messages'      => 0,
            'state'                => WhatsappDeliveryChannelStateEnum::IN_PROCESS,
        ]);

        foreach ($recipientRows as $row) {
            $this->storeRecipient($campaign, $channel, $row);
        }

        $channel->update([
            'number_messages' => $campaign->recipients()->where('whatsapp_delivery_channel_id', $channel->id)->count(),
            'state'           => WhatsappDeliveryChannelStateEnum::READY,
        ]);

        SendWhatsappDeliveryChannel::dispatch($channel->id)->delay(2);
    }

    /**
     * A number that cannot be turned into a session is skipped rather than stored: without
     * a session there is nothing to attach the sent message to, and the unique index on
     * (campaign, phone) means the row would block a later retry from succeeding.
     *
     * The format check repeats the one the audience and the selection already applied. It
     * is the last line before the Meta call, and by here there is no session to hang a
     * failure record on, so an unsendable number can only be skipped quietly.
     *
     * @param  array<string, mixed>  $row
     */
    private function storeRecipient(WhatsappCampaign $campaign, WhatsappDeliveryChannel $channel, array $row): void
    {
        $phone = (string) Arr::get($row, 'phone');

        if (!GetWhatsappRecipientsQuery::isSendablePhone($phone)) {
            return;
        }

        try {
            $session = StoreMetaChatSession::run(array_filter([
                'shop_id'      => $campaign->shop_id,
                'customer_id'  => Arr::get($row, 'customer_id'),
                'phone_number' => '+'.$phone,
                'name'         => Arr::get($row, 'name'),
            ], fn ($value) => $value !== null));
        } catch (Throwable) {
            return;
        }

        $customerId = Arr::get($row, 'customer_id') ?? $session->customer_id;

        WhatsappRecipient::firstOrCreate(
            [
                'whatsapp_campaign_id' => $campaign->id,
                'phone'                => $phone,
            ],
            [
                'whatsapp_delivery_channel_id' => $channel->id,
                'recipient_type'               => $customerId ? 'Customer' : 'MetaChatSession',
                'recipient_id'                 => $customerId ?: $session->id,
                'recipient_name'               => Arr::get($row, 'name') ?? $session->guest_identifier,
            ]
        );
    }
}
