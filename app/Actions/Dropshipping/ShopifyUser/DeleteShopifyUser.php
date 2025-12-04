<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 11 Jul 2024 10:16:14 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\ShopifyUser;

use App\Actions\Dropshipping\CustomerSalesChannel\UpdateCustomerSalesChannel;
use App\Actions\Dropshipping\Shopify\FulfilmentService\DeleteFulfilmentService;
use App\Actions\Dropshipping\Shopify\Webhook\DeleteWebhooksFromShopify;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;

class DeleteShopifyUser extends OrgAction
{
    use WithActionUpdate;


    public function handle(ShopifyUser $shopifyUser): void
    {
        if ($shopifyUser->trashed()) {
            return;
        }

        DeleteWebhooksFromShopify::run($shopifyUser);

        if ($shopifyUser->customerSalesChannel) {
            DeleteFulfilmentService::run($shopifyUser->customerSalesChannel, $shopifyUser->shopify_fulfilment_service_id);
        }

        $data = $shopifyUser->data;

        $ulid = (string)Str::ulid();

        data_set(
            $data,
            'original_data',
            [
                'name'  => $shopifyUser->name,
                'email' => $shopifyUser->email,
                'slug'  => $shopifyUser->slug,
            ]
        );

        $this->update($shopifyUser, [
            'name'   => $ulid,
            'slug'   => $ulid,
            'email'  => $ulid,
            'status' => false
        ]);

        if ($shopifyUser->customerSalesChannel && $shopifyUser->customerSalesChannel->status != CustomerSalesChannelStatusEnum::CLOSED) {
            UpdateCustomerSalesChannel::run($shopifyUser->customerSalesChannel, [
                'status' => CustomerSalesChannelStatusEnum::CLOSED
            ]);
        }

        $shopifyUser->delete();
    }


    public function asController(ActionRequest $request): void
    {
        /** @var \App\Models\CRM\Customer $customer */
        $customer = $request->user()->customer;

        $this->initialisationFromShop($customer->shop, $request);

        $this->handle($customer->shopifyUser);
    }


    public function inWebhook(ShopifyUser $shopifyUser): void
    {
        $this->handle($shopifyUser);
    }
}
