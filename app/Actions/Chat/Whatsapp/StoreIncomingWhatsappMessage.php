<?php

/*
 * Author: Eka Yudinata <ekayudinata@gmail.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Whatsapp;

use App\Actions\Chat\MetaChatSession\ReopenMetaChatSession;
use App\Actions\Chat\MetaChatSession\SetMetaChatMessageReaction;
use App\Actions\Chat\MetaChatSession\StoreMetaChatMessage;
use App\Actions\Chat\MetaChatSession\StoreMetaChatSession;
use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Events\BroadcastMetaChatListEvent;
use App\Events\BroadcastRealtimeMetaChat;
use App\Models\CRM\Customer;
use App\Models\Catalogue\Shop;
use App\Models\Chat\MetaChannel;
use App\Models\Chat\MetaChatMessage;
use App\Models\Chat\MetaChatSession;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreIncomingWhatsappMessage
{
    use AsAction;

    // TODO: make sure which queue is the best for this job, because this job is very important and urgent
    public string $jobQueue = 'urgent';

    /**
     * @param  array<string, mixed>  $value  The `changes[].value` node of a WhatsApp webhook
     */
    public function asJob(array $value): void
    {
        $this->handle($value);
    }

    /**
     * @param  array<string, mixed>  $value
     *
     * @throws \Throwable
     */
    public function handle(array $value): void
    {
        $phoneNumberId = (string) Arr::get($value, 'metadata.phone_number_id');

        $shop = Shop::whereJsonContains('settings->whatsapp->phone_number_id', $phoneNumberId)->first();

        if (!$shop) {
            Log::warning('WhatsApp message for unknown phone_number_id', [
                'phone_number_id' => $phoneNumberId,
            ]);

            return;
        }

        $metaChannel = MetaChannel::where('code', 'whatsapp')->first();

        if (!$metaChannel) {
            Log::warning('WhatsApp meta channel is not configured');

            return;
        }

        foreach (Arr::get($value, 'messages', []) as $message) {
            $this->storeMessage($shop, $metaChannel, $value, $message);
        }
    }

    /**
     * @param  array<string, mixed>  $value
     * @param  array<string, mixed>  $message
     */
    protected function storeMessage(Shop $shop, MetaChannel $metaChannel, array $value, array $message): void
    {
        $waMessageId = (string) Arr::get($message, 'id');

        if ($waMessageId === '') {
            return;
        }

        if (MetaChatMessage::where('meta_message_id', $waMessageId)->exists()) {
            return;
        }

        $digits      = preg_replace('/\D/', '', (string) Arr::get($message, 'from'));
        $profileName = Arr::get($value, 'contacts.0.profile.name');

        $metaChatSession = MetaChatSession::where('meta_channel_id', $metaChannel->id)
            ->where('shop_id', $shop->id)
            ->whereIn('phone_number', ['+'.$digits, $digits])
            ->latest('id')
            ->first();

        if (!$metaChatSession) {
            $customer = $this->findCustomer($shop, $digits);

            $metaChatSession = StoreMetaChatSession::run([
                'shop_id'      => $shop->id,
                'customer_id'  => $customer?->id,
                'phone_number' => '+'.$digits,
                'name'         => $profileName,
            ]);
        } elseif ($metaChatSession->status === ChatSessionStatusEnum::CLOSED) {
            $metaChatSession = ReopenMetaChatSession::make()->reopenToWaiting($metaChatSession);
        }

        $type     = (string) Arr::get($message, 'type');
        $waNode   = Arr::get($message, $type);
        $isMedia  = in_array($type, DownloadWhatsappMedia::MEDIA_TYPES, true);

        if ($type === 'reaction') {
            $this->storeReaction($metaChatSession, $waMessageId, (array) $waNode);
            $metaChatSession->update(['last_visitor_message_at' => now()]);

            return;
        }

        $quotedWaMessageId = (string) Arr::get($message, 'context.id');

        $metaChatMessage = StoreMetaChatMessage::run($metaChatSession, [
            'meta_message_id' => $waMessageId,
            'message_type'    => $this->messageType($type),
            'sender_type'     => ChatSenderTypeEnum::GUEST,
            'message_text'    => $this->messageText($type, $message, $waNode),
            'replied_to_id'   => $this->resolveQuotedMessageId($metaChatSession, $quotedWaMessageId),
            'metadata'        => [
                'wa_type'      => $type,
                'profile_name' => $profileName,
                'wa_payload'   => $type !== 'text' ? $waNode : null,
                'wa_context'   => Arr::get($message, 'context'),
            ],
        ]);

        if ($isMedia) {
            DownloadWhatsappMedia::run($metaChatMessage, $shop);
        }

        $metaChatSession->update(['last_visitor_message_at' => now()]);

        $metaChatMessage = $metaChatMessage->fresh(['attachment', 'metaChatSession']);

        BroadcastRealtimeMetaChat::dispatch($metaChatMessage);
        BroadcastMetaChatListEvent::dispatch($metaChatMessage, $metaChatSession->fresh());
    }

    protected function resolveQuotedMessageId(MetaChatSession $metaChatSession, string $quotedWaMessageId): ?int
    {
        if ($quotedWaMessageId === '') {
            return null;
        }

        return $metaChatSession->messages()
            ->where('meta_message_id', $quotedWaMessageId)
            ->value('id');
    }


    protected function messageType(string $type): ChatMessageTypeEnum
    {
        return match (true) {
            in_array($type, DownloadWhatsappMedia::IMAGE_TYPES, true) => ChatMessageTypeEnum::IMAGE,
            in_array($type, DownloadWhatsappMedia::MEDIA_TYPES, true) => ChatMessageTypeEnum::FILE,
            default                                                   => ChatMessageTypeEnum::TEXT,
        };
    }

    /**
     * WhatsApp puts the readable part of a message in a different place for every type.
     * Anything without one would otherwise be stored blank, which is how a customer
     * tapping a template button ended up as an empty bubble.
     *
     * @param  array<string, mixed>  $message
     */
    protected function messageText(string $type, array $message, mixed $waNode): ?string
    {
        return match ($type) {
            'text'        => Arr::get($message, 'text.body'),
            'button'      => Arr::get($message, 'button.text'),
            'interactive' => $this->interactiveText((array) $waNode),
            'location'    => $this->locationText((array) $waNode),
            'contacts'    => $this->contactsText((array) $waNode),
            default       => Arr::get($waNode, 'caption'),
        };
    }

    /**
     * @param  array<string, mixed>  $interactive
     */
    protected function interactiveText(array $interactive): ?string
    {
        $reply = Arr::get($interactive, 'button_reply') ?? Arr::get($interactive, 'list_reply');

        return Arr::get((array) $reply, 'title');
    }

    /**
     * @param  array<string, mixed>  $location
     */
    protected function locationText(array $location): ?string
    {
        $label = collect([Arr::get($location, 'name'), Arr::get($location, 'address')])
            ->filter()
            ->implode(' — ');

        if ($label !== '') {
            return $label;
        }

        $latitude  = Arr::get($location, 'latitude');
        $longitude = Arr::get($location, 'longitude');

        return $latitude && $longitude ? $latitude.', '.$longitude : null;
    }

    /**
     * @param  array<string, mixed>  $contacts
     */
    protected function contactsText(array $contacts): ?string
    {
        $names = collect($contacts)
            ->map(fn ($contact) => Arr::get((array) $contact, 'name.formatted_name'))
            ->filter();

        return $names->isEmpty() ? null : $names->implode(', ');
    }

    /**
     * @param  array<string, mixed>  $reaction
     */
    protected function storeReaction(MetaChatSession $metaChatSession, string $waMessageId, array $reaction): void
    {
        $targetId = (string) Arr::get($reaction, 'message_id');

        $target = $metaChatSession->messages()
            ->where('meta_message_id', $targetId)
            ->first();

        if (!$target) {
            Log::info('WhatsApp reaction for unknown message', [
                'meta_chat_session_id' => $metaChatSession->id,
                'target_message_id'    => $targetId,
            ]);

            return;
        }

        SetMetaChatMessageReaction::run(
            $target,
            ChatSenderTypeEnum::GUEST->value,
            null,
            (string) Arr::get($reaction, 'emoji', ''),
            $waMessageId
        );
    }

    protected function findCustomer(Shop $shop, string $digits): ?Customer
    {
        // ponytail: customers.phone has no index, so try the exact E.164 form first (~97% of rows)
        // and only fall back to the full digit-stripped scan. Add an index on the normalised phone
        // if inbound volume makes the fallback hurt.
        return Customer::where('shop_id', $shop->id)->where('phone', '+'.$digits)->first()
            ?? Customer::where('shop_id', $shop->id)
                ->whereRaw("regexp_replace(phone, '\\D', '', 'g') = ?", [$digits])
                ->first();
    }
}
