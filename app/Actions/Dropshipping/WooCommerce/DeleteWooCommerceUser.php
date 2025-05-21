<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 11 Jul 2024 10:16:14 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce;

use App\Actions\Dropshipping\CustomerSalesChannel\UpdateCustomerSalesChannel;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Models\CRM\WebUser;
use App\Models\Dropshipping\WooCommerceUser;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class DeleteWooCommerceUser extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(WooCommerceUser $wooCommerceUser): void
    {
        $wooCommerceUser->products()->detach();
        $wooCommerceUser->orders()->detach();
        if ($wooCommerceUser->customerSalesChannel) {
            UpdateCustomerSalesChannel::run($wooCommerceUser->customerSalesChannel, [
                'status' => CustomerSalesChannelStatusEnum::CLOSED
            ]);
        }

        $wooCommerceUser->forceDelete();
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction || $request->user() instanceof WebUser) {
            return true;
        }

        return $request->user()->authTo("crm.{$this->shop->id}.edit");
    }

    public function asController(ActionRequest $request): void
    {
        /** @var \App\Models\CRM\Customer $customer */
        $customer = $request->user()->customer;

        $this->initialisationFromShop($customer->shop, $request);

        $this->handle($customer->wooCommerceUser);
    }
}
