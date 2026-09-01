<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\WhatsappCampaign;

use App\Actions\Chat\MetaChatSession\StoreMetaChatMessage;
use App\Actions\Chat\Whatsapp\Concerns\WithWhatsappTemplatePayload;
use App\Actions\Chat\Whatsapp\StoreMetaTrackingEvent;
use App\Actions\Chat\Whatsapp\Templates\ResolveWhatsappTemplateTags;
use App\Enums\Comms\WhatsappCampaign\WhatsappCampaignStateEnum;
use App\Enums\Comms\WhatsappDeliveryChannel\WhatsappDeliveryChannelStateEnum;
use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\MetaTrackingEventTypeEnum;
use App\Models\Chat\MetaChatSession;
use App\Models\Comms\WhatsappCampaign;
use App\Models\Comms\WhatsappDeliveryChannel;
use App\Models\Comms\WhatsappRecipient;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Sends one delivery channel, a message at a time.
 *
 * Marketing templates go to /marketing_messages rather than /messages. The payload is the
 * same, but Meta accepts only marketing templates on that path and applies its own
 * marketing delivery rules to them.
 */
class SendWhatsappDeliveryChannel
{
    use AsAction;
    use WithWhatsappTemplatePayload;

    public string $jobQueue = 'long-send-emails';

    public function handle(int $whatsappDeliveryChannelId, bool $runOnlyInReady = true): void
    {
        $channel = WhatsappDeliveryChannel::find($whatsappDeliveryChannelId);

        if (!$channel) {
            return;
        }

        if ($runOnlyInReady && $channel->state != WhatsappDeliveryChannelStateEnum::READY) {
            return;
        }

        $campaign = $channel->whatsappCampaign;
        $template = $campaign?->metaMessageTemplate;

        if (!$campaign || !$template) {
            return;
        }

        [
            'phone_number_id' => $phoneNumberId,
            'access_token'    => $accessToken,
        ] = $this->whatsappCredentials($campaign->shop);

        if ($phoneNumberId === '' || $accessToken === '') {
            $channel->update(['state' => WhatsappDeliveryChannelStateEnum::STOPPED]);

            return;
        }

        $channel->update([
            'state'            => WhatsappDeliveryChannelStateEnum::SENDING,
            'start_sending_at' => now(),
        ]);

        /* ponytail: no throttling between sends. Meta caps throughput per phone number,
           but the 50 per channel split already spreads a campaign over separate jobs.
           If Meta starts rejecting, delay each channel or put a token bucket here. */
        foreach ($campaign->recipients()->where('whatsapp_delivery_channel_id', $channel->id)->get() as $recipient) {
            $campaign->refresh();

            if ($campaign->state == WhatsappCampaignStateEnum::STOPPED) {
                $channel->update(['state' => WhatsappDeliveryChannelStateEnum::STOPPED]);

                return;
            }

            if ($recipient->meta_chat_message_id) {
                continue;
            }

            $this->sendToRecipient($campaign, $recipient, $phoneNumberId, $accessToken);
        }

        $channel->update([
            'state'   => WhatsappDeliveryChannelStateEnum::SENT,
            'sent_at' => now(),
        ]);

        UpdateWhatsappCampaignSentState::run($campaign->refresh());
    }

    private function sendToRecipient(
        WhatsappCampaign $campaign,
        WhatsappRecipient $recipient,
        string $phoneNumberId,
        string $accessToken
    ): void {
        $session = $this->resolveSession($campaign, $recipient);

        if (!$session) {
            return;
        }

        $template = $campaign->metaMessageTemplate;
        $language = (string) $template->language;

        $tags   = Arr::get($template->data ?? [], 'merge_tags.body', []);
        $merged = $tags
            ? ResolveWhatsappTemplateTags::run($session, $tags)
            : ['values' => [], 'missing' => []];

        // WhatsApp rejects a blank parameter, so a template whose tags cannot all be
        // filled for this contact is not sent to them rather than sent broken.
        if ($merged['missing']) {
            $this->recordFailure($session, $campaign, $recipient, __('Missing :tags', [
                'tags' => implode(', ', $merged['missing']),
            ]));

            return;
        }

        $built = $this->templatePayload(
            $campaign->shop_id,
            $recipient->phone,
            $template->name,
            $language,
            $merged['values'],
            $phoneNumberId,
            $accessToken
        );

        if (!$built['ok']) {
            $this->recordFailure($session, $campaign, $recipient, $built['message']);

            return;
        }

        $response = Http::withToken($accessToken)->post(
            $this->whatsappEndpoint($phoneNumberId.'/marketing_messages'),
            $built['payload'] + ['recipient_type' => 'individual']
        );

        if ($response->failed()) {
            $this->recordFailure(
                $session,
                $campaign,
                $recipient,
                Arr::get($response->json(), 'error.message') ?: __('Failed to send WhatsApp message.')
            );

            return;
        }

        $headerMedia = $built['header_media'] ?? null;

        $metaChatMessage = StoreMetaChatMessage::run($session, [
            'meta_message_id' => Arr::get($response->json(), 'messages.0.id'),
            'message_type'    => $headerMedia ? $this->headerMessageType($headerMedia) : ChatMessageTypeEnum::TEXT,
            'sender_type'     => ChatSenderTypeEnum::SYSTEM,
            'message_text'    => $this->renderTemplateBody($campaign->shop_id, $template->name, $language, $merged['values']) ?: null,
            'media_id'        => $headerMedia?->id,
            'metadata'        => [
                'template'             => $template->name,
                'template_parameters'  => $merged['values'],
                'whatsapp_campaign_id' => $campaign->id,
                'wa_status'            => 'sent',
            ],
        ]);

        $recipient->update(['meta_chat_message_id' => $metaChatMessage->id]);

        $session->update(['last_agent_message_at' => now()]);
    }

    /**
     * The recipient row records who was messaged, which is the customer when there is one,
     * so the session is found by phone number the same way StoreMetaChatSession created it.
     */
    private function resolveSession(WhatsappCampaign $campaign, WhatsappRecipient $recipient): ?MetaChatSession
    {
        return MetaChatSession::where('shop_id', $campaign->shop_id)
            ->whereRaw("regexp_replace(phone_number, '[^0-9]', '', 'g') = ?", [$recipient->phone])
            ->latest('id')
            ->first();
    }

    /**
     * A failure is kept against the contact's thread rather than dropped, so the reason a
     * campaign missed someone is visible where the rest of their history is. The recipient
     * keeps a null meta_chat_message_id, which is what lets a re-run retry it.
     */
    private function recordFailure(MetaChatSession $session, WhatsappCampaign $campaign, WhatsappRecipient $recipient, string $reason): void
    {
        $metaChatMessage = StoreMetaChatMessage::run($session, [
            'message_type' => ChatMessageTypeEnum::TEXT,
            'sender_type'  => ChatSenderTypeEnum::SYSTEM,
            'metadata'     => [
                'whatsapp_campaign_id'   => $campaign->id,
                'whatsapp_recipient_id'  => $recipient->id,
                'wa_status'              => 'failed',
                'wa_error'               => $reason,
            ],
        ]);

        /* The send never reached Meta, so no wamid was issued and no status webhook will
           ever follow; this is the only record these failures get. */
        StoreMetaTrackingEvent::run(
            MetaTrackingEventTypeEnum::FAILED,
            $metaChatMessage,
            data: [
                'whatsapp_campaign_id'  => $campaign->id,
                'whatsapp_recipient_id' => $recipient->id,
                'error'                 => $reason,
            ]
        );
    }

    public string $commandSignature = 'whatsapp-campaign:send-channel {channel?}';

    public function asCommand(Command $command): int
    {
        if ($command->argument('channel')) {
            $channel = WhatsappDeliveryChannel::findOrFail($command->argument('channel'));
            $this->handle($channel->id, false);

            return 0;
        }

        foreach (WhatsappDeliveryChannel::where('state', WhatsappDeliveryChannelStateEnum::READY)->get() as $channel) {
            SendWhatsappDeliveryChannel::dispatch($channel->id);
        }

        return 0;
    }
}
