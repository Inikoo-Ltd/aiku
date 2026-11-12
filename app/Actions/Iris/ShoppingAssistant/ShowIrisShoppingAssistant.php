<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Iris\ShoppingAssistant;

use App\Actions\CRM\ShoppingAssistant\GetShoppingAssistantSettings;
use App\Actions\IrisAction;
use App\Actions\Web\Website\Layouts\FetchUsedProductsWebBlock;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * The assistant page. It is cached by URL like every storefront page, so it never depends on the
 * website switch or on who is asking: the page shows whether the assistant is available from the
 * logged-in data, and every endpoint it calls checks GetShoppingAssistantSettings for the customer.
 */
class ShowIrisShoppingAssistant extends IrisAction
{
    public function asController(ActionRequest $request): Response
    {
        $this->initialisation($request);

        return Inertia::render('ShoppingAssistant', [
            'web_block_family'      => data_get($this->website->liveProductsSnapshot?->layout, 'data'),
            'web_block_family_code' => FetchUsedProductsWebBlock::run($this->website),
            'products_in_range'     => $this->productsInRange(),
            'assistant_name'        => GetShoppingAssistantSettings::name($this->website),
        ]);
    }

    /**
     * Products for sale rounded down to the 500 (to the 100 below 500), as the landing page says "9,000+ products";
     * null for a small range, where a rounded figure would undersell it.
     */
    private function productsInRange(): ?int
    {
        $productsForSale = (int) $this->shop->stats()->value('number_products_status_for_sale');

        if ($productsForSale < 100) {
            return null;
        }

        return intdiv($productsForSale, 500) * 500 ?: intdiv($productsForSale, 100) * 100;
    }
}
