<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 01:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatSession;
use App\Models\Ordering\Order;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * What a customer reporting a problem has already given us since a moment: an order number
 * that is really one of this shop's orders, and how many photos or files. Read from what they
 * sent, never from what they claim, so the agent sees at a glance what is still missing.
 */
class GetChatClaimDetails
{
    use AsAction;

    /**
     * @return array{order_reference: ?string, photos: int, text: string}
     */
    public function handle(ChatSession|MetaChatSession $chatSession, ?Carbon $since = null): array
    {
        $messages = $chatSession->messages()
            ->whereIn('sender_type', [ChatSenderTypeEnum::GUEST, ChatSenderTypeEnum::USER])
            ->when($since, fn ($query) => $query->where('created_at', '>=', $since))
            ->when($chatSession instanceof ChatSession, fn ($query) => $query->with('media'))
            ->oldest('id')
            ->get();

        $text = $messages
            ->map(fn ($message) => trim((string) ($message->original_text ?? $message->message_text ?? '')))
            ->filter()
            ->join("\n");

        $photos = $messages->filter(function ($message) use ($chatSession) {
            if (in_array($message->message_type, [ChatMessageTypeEnum::IMAGE, ChatMessageTypeEnum::FILE], true)) {
                return true;
            }

            return $chatSession instanceof ChatSession && $message->attachedFiles()->isNotEmpty();
        })->count();

        return [
            'order_reference' => $chatSession->shop ? self::orderReference($chatSession->shop, $text) : null,
            'photos'          => $photos,
            'text'            => $text,
        ];
    }

    /**
     * What the inbox shows beside a conversation whose customer was asked for claim details
     * and has not been answered since: what has arrived, so the agent sees at a glance whether
     * the case is ready to decide. Nothing for everybody else, and no query for them either.
     *
     * @return array{order_reference: ?string, photos: int}|null
     */
    public static function forList(ChatSession|MetaChatSession $chatSession): ?array
    {
        $askedAt = data_get($chatSession->metadata, SendOutOfHoursReply::CLAIM_KEY);

        if (!$askedAt) {
            return null;
        }

        $answeredAt = $chatSession->last_agent_message_at ? Carbon::parse($chatSession->last_agent_message_at) : null;

        if ($answeredAt?->gt(Carbon::parse($askedAt))) {
            return null;
        }

        $details = self::run($chatSession, $answeredAt);

        return ['order_reference' => $details['order_reference'], 'photos' => $details['photos']];
    }

    /**
     * Customers write the number with the shop's letters or without them, so both are looked up,
     * and only a number that is really an order of this shop counts.
     */
    public static function orderReference(Shop $shop, string $text): ?string
    {
        preg_match_all('/\b([A-Za-z]{0,5})\s?-?(\d{4,9})\b/u', $text, $matches, PREG_SET_ORDER);

        if (!$matches) {
            return null;
        }

        $prefix = preg_replace('/\d+$/', '', (string) Order::where('shop_id', $shop->id)->latest('id')->value('reference'));

        $candidates = collect($matches)
            ->flatMap(fn (array $match) => array_filter([
                $match[1] !== '' ? strtoupper($match[1]).$match[2] : null,
                $prefix !== '' ? $prefix.$match[2] : null,
                $match[2],
            ]))
            ->unique()
            ->values()
            ->all();

        return Order::where('shop_id', $shop->id)->whereIn('reference', $candidates)->value('reference');
    }
}
