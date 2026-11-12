<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\WhatsappOutbox;

use App\Actions\Chat\MetaChatSession\StoreMetaChatMessage;
use App\Actions\Chat\MetaChatSession\StoreMetaChatSession;
use App\Actions\Chat\Whatsapp\Concerns\WithWhatsappTemplatePayload;
use App\Actions\Chat\Whatsapp\StoreMetaTrackingEvent;
use App\Actions\Chat\Whatsapp\Templates\ResolveWhatsappTemplateTags;
use App\Actions\Comms\WhatsappCampaign\SendWhatsappDeliveryChannel;
use App\Actions\Retina\CRM\WhatsappOptIn\SubscribeRetinaCustomerToWhatsappNewsletter;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\Outbox\OutboxStateEnum;
use App\Enums\CRM\Livechat\ChatMessageStateEnum;
use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\MetaTrackingEventTypeEnum;
use App\Models\Chat\MetaChatMessage;
use App\Models\Chat\MetaChatSession;
use App\Models\Chat\MetaMessageTemplate;
use App\Models\Comms\WhatsappOutbox;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * Sends a customer notification on WhatsApp. It is fired by the same events as the email of
 * the same code, but depends only on the shop's WhatsApp outbox for that code: the email
 * outbox being suspended or active makes no difference here.
 *
 * Utility templates go to /messages; a template Meta has moved to MARKETING is not sent,
 * because it would be billed and delivered as marketing.
 */
class SendCustomerNotificationWhatsapp
{
    use AsAction;
    use WithWhatsappTemplatePayload;

    public const UTILITY_CATEGORY = 'UTILITY';

    /**
     * Below Meta's default 80 messages a second per Cloud API number, so a bulk run sharing a
     * number with live chat leaves room for the agents' replies.
     */
    public const CLOUD_API_MESSAGES_PER_SECOND = 50;

    public string $jobQueue = 'whatsapp-send';

    public function handle(Customer $customer, OutboxCodeEnum $outboxCode, ?Order $order = null): ?MetaChatMessage
    {
        $shop = $customer->shop;

        if (!$shop || $shop->type === ShopTypeEnum::EXTERNAL) {
            return null;
        }

        $whatsappOutbox = $shop->whatsappOutboxes()->where('code', $outboxCode->value)->first();

        if (!$whatsappOutbox || !$this->isSendable($whatsappOutbox) || !$this->hasOptedIn($customer)) {
            return null;
        }

        return $this->send($whatsappOutbox, $customer, $order);
    }

    /**
     * Sends without checking the outbox state or the opt-in again, for callers that already
     * did: a bulk run checks them once when it picks its recipients.
     *
     * @param  array{product_ids?: array<int, int>, order_ids?: array<int, int>}  $context
     */
    public function send(WhatsappOutbox $whatsappOutbox, Customer $customer, ?Order $order = null, array $context = [], bool $throttleAll = false): ?MetaChatMessage
    {
        $shop     = $customer->shop;
        $template = $whatsappOutbox->metaMessageTemplate;

        if (!$template) {
            return null;
        }

        [
            'phone_number_id' => $phoneNumberId,
            'access_token'    => $accessToken,
        ] = $this->whatsappCredentials($shop);

        $phone = $this->customerPhone($customer);

        if ($phoneNumberId === '' || $accessToken === '' || !$phone) {
            return null;
        }

        $session = $this->resolveSession($customer, $phone);

        if (!$session) {
            return null;
        }

        $language = (string) $template->language;
        $tags     = Arr::get($template->data ?? [], 'merge_tags.body', []);
        $merged   = $tags ? ResolveWhatsappTemplateTags::run($session, $tags, null, $order, $context) : ['values' => [], 'missing' => []];

        $messageText = $this->renderTemplateBody($template->id, $shop->id, $template->name, $language, $merged['values']);

        if ($merged['missing']) {
            return $this->recordFailure($session, $whatsappOutbox, $template, $order, $messageText, __('Missing :tags', [
                'tags' => implode(', ', $merged['missing']),
            ]));
        }

        $built = $this->templatePayload($template->id, $shop->id, ltrim($phone, '+'), $template->name, $language, $merged['values'], $phoneNumberId, $accessToken);

        if (!$built['ok']) {
            return $this->recordFailure($session, $whatsappOutbox, $template, $order, $messageText, $built['message']);
        }

        $send = fn () => Http::withToken($accessToken)->post(
            $this->whatsappEndpoint($phoneNumberId.'/messages'),
            $built['payload'] + ['recipient_type' => 'individual']
        );

        $messagesPerSecond = $this->isWhatsappCoexistence($shop)
            ? SendWhatsappDeliveryChannel::COEXISTENCE_MESSAGES_PER_SECOND
            : ($throttleAll ? self::CLOUD_API_MESSAGES_PER_SECOND : null);

        $response = $messagesPerSecond
            ? Redis::throttle('whatsapp-send:'.$phoneNumberId)->allow($messagesPerSecond)->every(1)->block(60)->then($send, fn () => null)
            : $send();

        if (!$response) {
            return $this->recordFailure($session, $whatsappOutbox, $template, $order, $messageText, __('WhatsApp was too busy to send this message.'));
        }

        if ($response->failed()) {
            return $this->recordFailure(
                $session,
                $whatsappOutbox,
                $template,
                $order,
                $messageText,
                Arr::get($response->json(), 'error.message') ?: __('Failed to send WhatsApp message.')
            );
        }

        $headerMedia = $built['header_media'] ?? null;

        $metaChatMessage = StoreMetaChatMessage::run($session, [
            'meta_message_id'    => Arr::get($response->json(), 'messages.0.id'),
            'whatsapp_outbox_id' => $whatsappOutbox->id,
            'message_type'       => $headerMedia ? $this->headerMessageType($headerMedia) : ChatMessageTypeEnum::TEXT,
            'sender_type'        => ChatSenderTypeEnum::SYSTEM_NOTIFICATION,
            'state'              => ChatMessageStateEnum::SENT,
            'message_text'       => $messageText ?: null,
            'media_id'           => $headerMedia?->id,
            'metadata'           => $this->metadata($template, $order) + [
                'template_parameters' => $merged['values'],
                'wa_status'           => 'sent',
            ],
        ]);

        $whatsappOutbox->update(['last_sent_at' => now()]);

        $session->update(['last_agent_message_at' => now()]);

        SendWhatsappDeliveryChannel::make()->parkSession($session);

        return $metaChatMessage;
    }

    public function isSendable(WhatsappOutbox $whatsappOutbox): bool
    {
        return $whatsappOutbox->state === OutboxStateEnum::ACTIVE
            && $this->isUsableTemplate($whatsappOutbox->metaMessageTemplate);
    }

    public function isUsableTemplate(?MetaMessageTemplate $template): bool
    {
        return $template
            && $template->status === 'APPROVED'
            && strtoupper((string) $template->category) === self::UTILITY_CATEGORY;
    }

    public function hasOptedIn(Customer $customer): bool
    {
        return (bool) $customer->comms?->is_subscribed_to_whatsapp_notifications;
    }

    /**
     * The checkbox is only worth showing where it can lead to a message: the shop has a
     * WhatsApp number, at least one WhatsApp outbox is active, and the customer has a number
     * we could send to.
     */
    public function canOfferOptIn(Customer $customer): bool
    {
        $shop = $customer->shop;

        if (!$shop || !$customer->comms || $this->whatsappCredentials($shop)['phone_number_id'] === '') {
            return false;
        }

        return $this->customerPhone($customer) !== null
            && $shop->whatsappOutboxes()
                ->where('state', OutboxStateEnum::ACTIVE->value)
                ->whereNotNull('meta_message_template_id')
                ->exists();
    }

    public function customerPhone(Customer $customer): ?string
    {
        return SubscribeRetinaCustomerToWhatsappNewsletter::normalisePhone($customer->marketing_whatsapp_phone)
            ?? SubscribeRetinaCustomerToWhatsappNewsletter::normalisePhone($customer->phone);
    }

    private function resolveSession(Customer $customer, string $phone): ?MetaChatSession
    {
        try {
            return StoreMetaChatSession::run([
                'shop_id'      => $customer->shop_id,
                'customer_id'  => $customer->id,
                'phone_number' => $phone,
                'phone_source' => $customer->marketing_whatsapp_phone
                    ? StoreMetaChatSession::PHONE_SOURCE_MARKETING_WHATSAPP
                    : StoreMetaChatSession::PHONE_SOURCE_MAIN,
            ]);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(MetaMessageTemplate $template, ?Order $order): array
    {
        return array_filter([
            'template'                 => $template->name,
            'meta_message_template_id' => $template->id,
            'order_id'                 => $order?->id,
        ], fn ($value) => $value !== null);
    }

    /**
     * Kept in the customer's thread with the text it would have sent, so staff see both what
     * the customer missed and why. No status webhook follows a send Meta never accepted.
     */
    private function recordFailure(MetaChatSession $session, WhatsappOutbox $whatsappOutbox, MetaMessageTemplate $template, ?Order $order, string $messageText, string $reason): MetaChatMessage
    {
        $metaChatMessage = StoreMetaChatMessage::run($session, [
            'whatsapp_outbox_id' => $whatsappOutbox->id,
            'message_type'       => ChatMessageTypeEnum::TEXT,
            'sender_type'        => ChatSenderTypeEnum::SYSTEM_NOTIFICATION,
            'state'              => ChatMessageStateEnum::FAILED,
            'message_text'       => $messageText ?: null,
            'metadata'           => $this->metadata($template, $order) + [
                'wa_status' => 'failed',
                'wa_error'  => ['message' => $reason],
            ],
        ]);

        StoreMetaTrackingEvent::run(
            MetaTrackingEventTypeEnum::FAILED,
            $metaChatMessage,
            data: [
                'whatsapp_outbox_id' => $whatsappOutbox->id,
                'order_id'           => $order?->id,
                'error'              => $reason,
            ]
        );

        return $metaChatMessage;
    }
}
