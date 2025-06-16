<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 11 Jul 2024 10:16:14 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\ShopifyUser;

use App\Actions\Dropshipping\CustomerSalesChannel\UpdateCustomerSalesChannel;
use App\Actions\Dropshipping\Shopify\Webhook\DeleteWebhooksFromShopify;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Models\CRM\WebUser;
use App\Models\Dropshipping\ShopifyUser;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Random\RandomException;

class DeleteRetinaShopifyUser extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    /**
     * @throws RandomException
     */
    public function handle(ShopifyUser $shopifyUser): void
    {
        DeleteWebhooksFromShopify::dispatch($shopifyUser);

        $randomNumber = random_int(00, 99);
        $deletedSuffix = 'deleted-' . $randomNumber;

        $this->update($shopifyUser, [
            'name' => $shopifyUser->name . $deletedSuffix,
            'slug' => $shopifyUser->slug . $deletedSuffix,
            'email' => $shopifyUser->email . $deletedSuffix,
            'status' => false
        ]);

        if ($shopifyUser->customerSalesChannel) {
            UpdateCustomerSalesChannel::run($shopifyUser->customerSalesChannel, [
                'status' => CustomerSalesChannelStatusEnum::CLOSED
            ]);
        }

        $shopifyUser->delete();
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction || $request->user() instanceof WebUser) {
            return true;
        }

        return $request->user()->authTo("crm.{$this->shop->id}.edit");
    }

    /**
     * @throws \Random\RandomException
     */
    public function asController(ActionRequest $request): void
    {
        /** @var \App\Models\CRM\Customer $customer */
        $customer = $request->user()->customer;

        $this->initialisationFromShop($customer->shop, $request);

        $this->handle($customer->shopifyUser);
    }

    /**
     * @throws \Random\RandomException
     */
    public function inWebhook(ShopifyUser $shopifyUser): void
    {
        $this->handle($shopifyUser);
    }
}
