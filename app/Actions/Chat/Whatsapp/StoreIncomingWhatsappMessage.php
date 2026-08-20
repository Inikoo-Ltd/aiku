<?php

/*
 * Author: Eka Yudinata <ekayudinata@gmail.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Whatsapp;

use App\Actions\Chat\MetaChatSession\StoreMetaChatMessage;
use App\Actions\Chat\MetaChatSession\StoreMetaChatSession;
use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
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
            ->where('status', '!=', ChatSessionStatusEnum::CLOSED)
            ->first();

        if (!$metaChatSession) {
            $customer = $this->findCustomer($shop, $digits);

            if (!$customer) {
                Log::info('WhatsApp message from unknown phone', [
                    'from'         => $digits,
                    'profile_name' => $profileName,
                    'shop_id'      => $shop->id,
                    'type'         => Arr::get($message, 'type'),
                    'message_text' => Arr::get($message, 'text.body'),
                ]);

                return;
            }

            $metaChatSession = StoreMetaChatSession::run([
                'shop_id'     => $shop->id,
                'customer_id' => $customer->id,
            ]);
        }

        $type = (string) Arr::get($message, 'type');

        StoreMetaChatMessage::run($metaChatSession, [
            'meta_message_id' => $waMessageId,
            'message_type'    => match ($type) {
                'text'  => ChatMessageTypeEnum::TEXT,
                'image' => ChatMessageTypeEnum::IMAGE,
                default => ChatMessageTypeEnum::FILE,
            },
            'sender_type'     => ChatSenderTypeEnum::GUEST,
            'message_text'    => Arr::get($message, 'text.body'),
            'metadata'        => [
                'wa_type'      => $type,
                'profile_name' => $profileName,
                // ponytail: raw node kept as-is; media (image/document/audio) is not downloaded yet
                'wa_payload'   => $type !== 'text' ? Arr::get($message, $type) : null,
            ],
        ]);

        $metaChatSession->update(['last_visitor_message_at' => now()]);
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
