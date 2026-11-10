<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\ShoppingAssistant;

use App\Models\Web\Website;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The monthly AI spend cap of a website's shopping assistant, in US dollars: the website's own
 * budget or the config default, against the cost of every message of this calendar month. The
 * budget is read from the database, not from the website passed in, because the storefront's
 * copy comes from a cache that is not flushed when the budget changes.
 *
 * ponytail: the result is cached 60 s and checked before a question is queued, so questions asked
 * at the same moment can overshoot the cap by a few answers, and a turn whose job is killed by its
 * timeout stores no cost, so the spend can be slightly under the provider's bill; reserve an amount
 * per accepted question if the overshoot ever matters.
 */
class GetShoppingAssistantBudget
{
    use AsAction;

    /**
     * @return array{monthly: float, spent: float, paused: bool, resumes_on: string}
     */
    public function handle(Website $website): array
    {
        return Cache::remember(self::cacheKey($website), 60, function () use ($website) {
            $settings = json_decode((string) DB::table('websites')->where('id', $website->id)->value('settings'), true);
            $override = data_get($settings, 'shopping_assistant.monthly_budget');
            $monthly  = is_numeric($override) ? (float) $override : (float) config('services.shopping_assistant.monthly_budget');

            $spent = (float) DB::table('shopping_assistant_messages')
                ->join('shopping_assistant_conversations', 'shopping_assistant_conversations.id', '=', 'shopping_assistant_messages.shopping_assistant_conversation_id')
                ->where('shopping_assistant_conversations.website_id', $website->id)
                ->where('shopping_assistant_messages.created_at', '>=', now()->startOfMonth())
                ->sum('shopping_assistant_messages.cost');

            return [
                'monthly'    => $monthly,
                'spent'      => $spent,
                'paused'     => $spent >= $monthly,
                'resumes_on' => now()->startOfMonth()->addMonth()->toDateString(),
            ];
        });
    }

    public static function forget(Website $website): void
    {
        Cache::forget(self::cacheKey($website));
    }

    private static function cacheKey(Website $website): string
    {
        return 'shopping_assistant_budget:'.$website->id;
    }
}
