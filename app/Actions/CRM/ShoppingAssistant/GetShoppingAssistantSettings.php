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
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The storefront gate of the shopping assistant: on only when the website switch is on, the shop
 * sells B2B and the customer belongs to that shop. The switch is read first, so a website with it
 * off costs no query. Where a new question is offered or accepted ($forNewQuestion) the website's
 * monthly AI budget must not be spent; reading an answer, clicks and basket adds stay on.
 */
class GetShoppingAssistantSettings
{
    use AsAction;

    /**
     * @return array{name: ?string}|null
     */
    public function handle(Website $website, ?Customer $customer, bool $forNewQuestion = false): ?array
    {
        if (!data_get($website->settings, 'shopping_assistant.enabled') || !$customer || $customer->shop_id !== $website->shop_id || $website->shop->type !== ShopTypeEnum::B2B) {
            return null;
        }

        if ($forNewQuestion && GetShoppingAssistantBudget::run($website)['paused']) {
            return null;
        }

        return ['name' => data_get($website->settings, 'shopping_assistant.name') ?: null];
    }
}
