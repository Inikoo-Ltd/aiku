<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\ShoppingAssistant;

use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantConversationStatusEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantEntryPointEnum;
use App\Models\CRM\Customer;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantConversation;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * ponytail: the conversation speaks the shop's language, not the web user's. Read the web user's
 * language here once the assistant is reachable from the website.
 */
class StartShoppingAssistantConversation
{
    use AsAction;

    /**
     * @param  array{web_user_id?: ?int, website_visitor_id?: ?int}  $context
     */
    public function handle(Customer $customer, ShoppingAssistantEntryPointEnum $entryPoint, array $context = []): ShoppingAssistantConversation
    {
        $shop = $customer->shop;

        return ShoppingAssistantConversation::create([
            'ulid'               => (string) Str::ulid(),
            'group_id'           => $customer->group_id,
            'organisation_id'    => $customer->organisation_id,
            'shop_id'            => $customer->shop_id,
            'website_id'         => $shop->website?->id,
            'customer_id'        => $customer->id,
            'web_user_id'        => $context['web_user_id'] ?? null,
            'website_visitor_id' => $context['website_visitor_id'] ?? null,
            'entry_point'        => $entryPoint,
            'language_code'      => $shop->language?->code ?? 'en',
            'status'             => ShoppingAssistantConversationStatusEnum::OPEN,
        ]);
    }
}
