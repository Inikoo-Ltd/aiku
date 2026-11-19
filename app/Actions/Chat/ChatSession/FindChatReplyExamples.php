<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Helpers\AI\EmbedTexts;
use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatReplyExample;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * How this shop's own staff answered the customer messages nearest in meaning to this one, so a
 * CS draft gives the shop's house answer in its words and length (INI-402: in HU 15 of 44
 * discarded drafts lost to a standard reply the drafts did not know). Same shop only: shops of an
 * organisation write in different languages and keep different habits. Recent replies only, as
 * answers change. Null when the search is not available (no text, no embeddings key, provider
 * failed): the caller says nothing rather than "staff never answered this".
 *
 * @return ?array<int, array{customer: string, staff_replied: string, at: string}>
 */
class FindChatReplyExamples
{
    use AsAction;

    public const int LIMIT = 5;

    public const int RECENT_MONTHS = 18;

    public function handle(Shop $shop, string $customerWrote, int $limit = self::LIMIT): ?array
    {
        $customerWrote = trim($customerWrote);
        if ($customerWrote === '') {
            return null;
        }

        $vector = EmbedTexts::run([mb_substr($customerWrote, 0, 2000)])[0] ?? null;
        if (!$vector) {
            return null;
        }

        return ChatReplyExample::where('shop_id', $shop->id)
            ->whereNotNull('embedding')
            ->where('replied_at', '>=', now()->subMonths(self::RECENT_MONTHS))
            ->orderByVectorDistance('embedding', $vector)
            ->limit($limit)
            ->get(['customer_wrote', 'reply', 'replied_at'])
            ->map(fn (ChatReplyExample $example) => [
                'customer'      => self::withoutCustomerDetails(mb_substr($example->customer_wrote, 0, 400)),
                'staff_replied' => self::withoutCustomerDetails(mb_substr($example->reply, 0, 1200)),
                'at'            => $example->replied_at->toDateString(),
            ])
            ->all();
    }

    /**
     * Another customer's details stay out of the draft: the greeting line (it carries their name),
     * email addresses, and references with digits (orders, invoices, deliveries, phone numbers) are
     * replaced, so the draft can copy the answer but not that customer.
     */
    public static function withoutCustomerDetails(string $text): string
    {
        $text = preg_replace('/\A[^\n]{0,40}[,!]\s*\n/u', '', trim($text)) ?? $text;
        $text = preg_replace('/[\p{L}\d._%+-]+@[\p{L}\d.-]+\.\p{L}{2,}/u', '[email]', $text) ?? $text;

        return preg_replace('/(?<![\p{L}\d])(?=[\p{L}\d\/-]*\d)[\p{L}\d][\p{L}\d\/-]{3,}(?![\p{L}\d])/u', '[ref]', $text) ?? $text;
    }
}
