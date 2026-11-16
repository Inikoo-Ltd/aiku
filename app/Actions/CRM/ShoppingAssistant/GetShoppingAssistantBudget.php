<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\ShoppingAssistant;

use App\Models\CRM\Customer;
use App\Models\Web\Website;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The monthly AI spend cap of a website's shopping assistant, in US dollars: the website's own
 * budget or the config default, against the cost of every model call of this calendar month
 * (shopping_assistant_model_calls, written as each call returns, so a turn killed by its timeout
 * still counts what it spent). The budget is read from the database, not from the website passed
 * in, because the storefront's copy comes from a cache that is not flushed when the budget changes.
 *
 * ponytail: the result is cached 60 s and checked before a question is queued, so questions asked
 * at the same moment can overshoot the cap by a few answers; reserve an amount per accepted
 * question if the overshoot ever matters.
 */
class GetShoppingAssistantBudget
{
    use AsAction;

    public const int PROJECTION_DAYS = 7;

    /**
     * @return array{monthly: float, spent: float, paused: bool, resumes_on: string}
     */
    public function handle(Website $website): array
    {
        return Cache::remember(self::cacheKey($website), 60, function () use ($website) {
            $settings = json_decode((string) DB::table('websites')->where('id', $website->id)->value('settings'), true);
            $override = data_get($settings, 'shopping_assistant.monthly_budget');
            $monthly  = is_numeric($override) ? (float) $override : (float) config('services.shopping_assistant.monthly_budget');

            $spent = (float) DB::table('shopping_assistant_model_calls')
                ->join('shopping_assistant_conversations', 'shopping_assistant_conversations.id', '=', 'shopping_assistant_model_calls.shopping_assistant_conversation_id')
                ->where('shopping_assistant_conversations.website_id', $website->id)
                ->where('shopping_assistant_model_calls.created_at', '>=', now()->startOfMonth())
                ->sum('shopping_assistant_model_calls.cost');

            return [
                'monthly'    => $monthly,
                'spent'      => $spent,
                'paused'     => $spent >= $monthly,
                'resumes_on' => now()->startOfMonth()->addMonth()->toDateString(),
            ];
        });
    }

    /**
     * What each website will have spent by the end of this calendar month if it goes on as in the
     * last PROJECTION_DAYS days: this month's spend plus the daily average of those days (of the
     * days since its first answer when it started later, never less than one day) for the days
     * left. For the report only: it is not cached and not read when a question is asked.
     *
     * @param  array<int, int>  $websiteIds
     *
     * @return array<int, float> by website id; a website with no spend yet is left out
     */
    public static function projections(array $websiteIds): array
    {
        $monthStart = now()->startOfMonth();
        $recentFrom = now()->subDays(self::PROJECTION_DAYS);
        $daysLeft   = abs(now()->diffInSeconds(now()->endOfMonth())) / 86400;

        return DB::table('shopping_assistant_model_calls')
            ->join('shopping_assistant_conversations', 'shopping_assistant_conversations.id', '=', 'shopping_assistant_model_calls.shopping_assistant_conversation_id')
            ->whereIn('shopping_assistant_conversations.website_id', $websiteIds)
            ->groupBy('shopping_assistant_conversations.website_id')
            ->selectRaw('shopping_assistant_conversations.website_id, min(shopping_assistant_model_calls.created_at) as first_call_at, coalesce(sum(shopping_assistant_model_calls.cost) filter (where shopping_assistant_model_calls.created_at >= ?), 0) as spent, coalesce(sum(shopping_assistant_model_calls.cost) filter (where shopping_assistant_model_calls.created_at >= ?), 0) as recent', [$monthStart, $recentFrom])
            ->get()
            ->mapWithKeys(function ($row) use ($recentFrom, $daysLeft) {
                $from = Carbon::parse($row->first_call_at)->max($recentFrom);
                $days = max(1, abs($from->diffInSeconds(now())) / 86400);

                return [(int) $row->website_id => round((float) $row->spent + (float) $row->recent / $days * $daysLeft, 2)];
            })
            ->all();
    }

    /**
     * What one customer's questions on this website cost this calendar month, kept 60 s like the
     * website total. A customer who has never been invoiced may spend at most
     * services.shopping_assistant.non_buyer_monthly_budget, so throwaway accounts cannot drain a
     * website's budget; customers who have bought are held only by the rate limit and the website budget.
     */
    public static function customerSpent(Website $website, Customer $customer): float
    {
        return Cache::remember(self::customerCacheKey($website, $customer), 60, fn () => (float) DB::table('shopping_assistant_model_calls')
            ->join('shopping_assistant_conversations', 'shopping_assistant_conversations.id', '=', 'shopping_assistant_model_calls.shopping_assistant_conversation_id')
            ->where('shopping_assistant_conversations.website_id', $website->id)
            ->where('shopping_assistant_conversations.customer_id', $customer->id)
            ->where('shopping_assistant_model_calls.created_at', '>=', now()->startOfMonth())
            ->sum('shopping_assistant_model_calls.cost'));
    }

    public static function isCustomerCapped(Website $website, Customer $customer): bool
    {
        if ((int) $customer->stats?->number_invoices_type_invoice > 0) {
            return false;
        }

        return self::customerSpent($website, $customer) >= (float) config('services.shopping_assistant.non_buyer_monthly_budget');
    }

    public static function forgetCustomer(Website $website, Customer $customer): void
    {
        Cache::forget(self::customerCacheKey($website, $customer));
    }

    private static function customerCacheKey(Website $website, Customer $customer): string
    {
        return 'shopping_assistant_customer_spent:'.$website->id.':'.$customer->id;
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
