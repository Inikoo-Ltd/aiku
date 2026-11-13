<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\ShoppingAssistant;

use App\Actions\Traits\Authorisations\WithShopPpcPermissions;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantConversation;
use App\Models\SysAdmin\User;
use App\Notifications\ShoppingAssistantHeavyCustomerNotification;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Emails the weekly summary recipients once a month when one customer's questions have cost
 * services.shopping_assistant.heavy_customer_share of their website's monthly budget, so staff see
 * one account eating a site's budget before it switches the assistant off for everybody. Nobody is
 * blocked: customers who have bought have no allowance of their own. At most one email per
 * customer, website and month.
 */
class AlertShoppingAssistantHeavyCustomer
{
    use AsAction;
    use WithShopPpcPermissions;

    public function handle(ShoppingAssistantConversation $conversation): int
    {
        $website  = $conversation->website;
        $customer = $conversation->customer;
        if (!$website || !$customer) {
            return 0;
        }

        GetShoppingAssistantBudget::forgetCustomer($website, $customer);

        $monthly   = GetShoppingAssistantBudget::run($website)['monthly'];
        $threshold = $monthly * (float) config('services.shopping_assistant.heavy_customer_share');
        $spent     = GetShoppingAssistantBudget::customerSpent($website, $customer);
        if ($threshold <= 0 || $spent < $threshold) {
            return 0;
        }

        if (!Cache::add('shopping_assistant_heavy_customer:'.$website->id.':'.$customer->id.':'.now()->format('Y-m'), true, now()->endOfMonth())) {
            return 0;
        }

        $recipients = User::where('group_id', $website->group_id)
            ->where('status', true)
            ->whereIn('id', Arr::get($website->group->settings, 'shopping_assistant.weekly_summary_user_ids', []) ?: [0])
            ->get()
            ->filter(fn (User $user) => $this->canViewGroupShoppingAssistant($user));

        $notification = new ShoppingAssistantHeavyCustomerNotification($website->domain, $customer->name ?: $customer->reference, $spent, $monthly);
        $recipients->each(fn (User $user) => rescue(fn () => $user->notify($notification)));

        return $recipients->count();
    }
}
