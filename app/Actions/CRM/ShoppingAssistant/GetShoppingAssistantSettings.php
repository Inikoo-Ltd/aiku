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
 */
class GetShoppingAssistantSettings
{
    use AsAction;

    /**
     * @return array{name: string, hand_over: bool}|null
     */
    public function handle(Website $website, ?Customer $customer, bool $forNewQuestion = false): ?array
    {
        if (!data_get($website->settings, 'shopping_assistant.enabled') || !$customer || $customer->shop_id !== $website->shop_id || !in_array($website->shop->type, [ShopTypeEnum::B2B, ShopTypeEnum::DROPSHIPPING], true)) {
            return null;
        }

        if ($forNewQuestion && (GetShoppingAssistantBudget::run($website)['paused'] || GetShoppingAssistantBudget::isCustomerCapped($website, $customer))) {
            return null;
        }

        return ['name' => self::name($website), 'hand_over' => GetShoppingAssistantHandOverRoute::isOffered($website, $customer)];
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
