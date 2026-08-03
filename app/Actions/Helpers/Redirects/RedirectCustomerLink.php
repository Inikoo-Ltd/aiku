<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Redirects;

use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectCustomerLink extends OrgAction
{
    public function handle(Customer $customer): RedirectResponse
    {
        $shop = $customer->shop;

        if ($shop->type == ShopTypeEnum::FULFILMENT) {
            return $this->getFulfilmentRedirect($customer, $shop);
        }

        return $this->getShopRedirect($customer, $shop);
    }

    private function getFulfilmentRedirect(Customer $customer, Shop $shop): RedirectResponse
    {
        return Redirect::to(route('grp.org.fulfilments.show.crm.customers.show', [
            $shop->organisation->slug,
            $shop->fulfilment->slug,
            $customer->fulfilmentCustomer->slug,
        ]));
    }

    private function getShopRedirect(Customer $customer, Shop $shop): RedirectResponse
    {
        return Redirect::to(route('grp.org.shops.show.crm.customers.show', [
            $shop->organisation->slug,
            $shop->slug,
            $customer->slug,
        ]));
    }

    public function asController(Customer $customer, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromShop($customer->shop, $request);

        return $this->handle($customer);
    }
}
