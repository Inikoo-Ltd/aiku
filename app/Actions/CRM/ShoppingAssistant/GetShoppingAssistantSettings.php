<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\ShoppingAssistant;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\CRM\Customer;
use App\Models\Web\Website;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The storefront gate of the shopping assistant: on only when the website switch is on, the shop
 * sells B2B or dropshipping and the customer belongs to that shop. The switch is read first, so a website with it
 * off costs no query. Where a new question is offered or accepted ($forNewQuestion) the website's
 * monthly AI budget must not be spent, nor a never-invoiced customer's own allowance; reading an answer, clicks and basket adds stay on.
 * hand_over says whether a person can be offered at all (see GetShoppingAssistantHandOverRoute::isOffered).
 * expected_seconds is how long a question answered by the model usually takes on this website, for
 * the waiting animation's honest progress bar.
 */
class GetShoppingAssistantSettings
{
    use AsAction;

    /**
     * @return array{name: string, hand_over: bool, expected_seconds: int}|null
     */
    public function handle(Website $website, ?Customer $customer, bool $forNewQuestion = false): ?array
    {
        if (!data_get($website->settings, 'shopping_assistant.enabled') || !$customer || $customer->shop_id !== $website->shop_id || !in_array($website->shop->type, [ShopTypeEnum::B2B, ShopTypeEnum::DROPSHIPPING], true)) {
            return null;
        }

        if ($forNewQuestion && (GetShoppingAssistantBudget::run($website)['paused'] || GetShoppingAssistantBudget::isCustomerCapped($website, $customer))) {
            return null;
        }

        return ['name' => self::name($website), 'hand_over' => GetShoppingAssistantHandOverRoute::isOffered($website, $customer), 'expected_seconds' => self::expectedSeconds($website)];
    }

    /**
     * The name customers see: the website's own, else the one set for the whole group, else the
     * config default. The group's name is read from the database and kept 60 seconds, as the
     * storefront has no cached copy of the group settings to flush.
     */
    public static function name(Website $website): string
    {
        return data_get($website->settings, 'shopping_assistant.name')
            ?: self::groupName($website->group_id)
            ?: (string) config('services.shopping_assistant.name');
    }

    public const int EXPECTED_SECONDS = 10;

    /**
     * The median time of the last 7 days' answers written by the model on this website, kept an
     * hour; answers that need no model (plain searches, codes, greetings, thanks, hand-overs) are left
     * out because they come back at once. EXPECTED_SECONDS until there are enough answers.
     */
    public static function expectedSeconds(Website $website): int
    {
        return Cache::remember('shopping_assistant_expected_seconds:'.$website->id, 3600, function () use ($website) {
            $row = DB::table('shopping_assistant_messages')
                ->join('shopping_assistant_conversations', 'shopping_assistant_conversations.id', '=', 'shopping_assistant_messages.shopping_assistant_conversation_id')
                ->where('shopping_assistant_conversations.website_id', $website->id)
                ->where('shopping_assistant_messages.role', 'assistant')
                ->where('shopping_assistant_messages.created_at', '>=', now()->subDays(7))
                ->whereNotNull('shopping_assistant_messages.latency_ms')
                ->where('shopping_assistant_messages.model_calls', '>', 0)
                ->selectRaw('count(*) as answers, percentile_cont(0.5) within group (order by shopping_assistant_messages.latency_ms) as median_ms')
                ->first();

            return $row && $row->answers >= 20 ? max(3, (int) round($row->median_ms / 1000)) : self::EXPECTED_SECONDS;
        });
    }

    public static function nameInGroup(int $groupId): string
    {
        return self::groupName($groupId) ?: (string) config('services.shopping_assistant.name');
    }

    public static function groupName(int $groupId): string
    {
        return Cache::remember(self::groupNameCacheKey($groupId), 60, function () use ($groupId) {
            $settings = json_decode((string) DB::table('groups')->where('id', $groupId)->value('settings'), true);

            return trim((string) data_get($settings, 'shopping_assistant.name'));
        });
    }

    public static function forgetGroupName(int $groupId): void
    {
        Cache::forget(self::groupNameCacheKey($groupId));
    }

    private static function groupNameCacheKey(int $groupId): string
    {
        return 'shopping_assistant_group_name:'.$groupId;
    }
}
