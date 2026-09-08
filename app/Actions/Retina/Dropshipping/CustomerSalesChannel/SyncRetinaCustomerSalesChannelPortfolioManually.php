<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 02 Jul 2025 13:35:13 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\CustomerSalesChannel;

use App\Actions\Dropshipping\Shopify\Product\UpdateInventoryInShopifyCustomerSalesChannel;
use App\Actions\Dropshipping\Tiktok\Product\UpdateInventoryTiktokProducts;
use App\Actions\Dropshipping\WooCommerce\Product\UpdateInventoryInEbayPortfolio;
use App\Actions\Dropshipping\WooCommerce\Product\UpdateInventoryInWooPortfolio;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class SyncRetinaCustomerSalesChannelPortfolioManually extends RetinaAction
{
    use WithActionUpdate;

    public function authorize(ActionRequest $request): bool
    {
        /** @var CustomerSalesChannel $customerSalesChannel */
        $customerSalesChannel = $request->route('customerSalesChannel');

        return $customerSalesChannel->customer_id == $this->customer->id;
    }

    public function handle(CustomerSalesChannel $customerSalesChannel): ?CustomerSalesChannel
    {
        $platformUser = $customerSalesChannel->user;

        if (! $platformUser) {
            return null;
        }

        switch ($customerSalesChannel->platform->type) {
            case PlatformTypeEnum::SHOPIFY:
                UpdateInventoryInShopifyCustomerSalesChannel::run($customerSalesChannel);
                break;
            case PlatformTypeEnum::WOOCOMMERCE:
                UpdateInventoryInWooPortfolio::run($customerSalesChannel);
                break;
            case PlatformTypeEnum::EBAY:
                UpdateInventoryInEbayPortfolio::run($customerSalesChannel);
                break;
            case PlatformTypeEnum::TIKTOK:
                UpdateInventoryTiktokProducts::run($customerSalesChannel);
                break;
            default:
                return null;
        }

        return $customerSalesChannel;
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): ?CustomerSalesChannel
    {
        $this->initialisation($request);

        return $this->handle($customerSalesChannel);
    }

    public function htmlResponse(?CustomerSalesChannel $customerSalesChannel): RedirectResponse
    {
        if (!$customerSalesChannel) {
            return Redirect::back()->with('notification', [
                'status'      => 'error',
                'title'       => __('Stock update failed'),
                'description' => __('This channel is not connected to the platform, so stock cannot be updated.'),
            ]);
        }

        return Redirect::back()->with('notification', [
            'status'      => 'success',
            'title'       => __('Stock update started'),
            'description' => __('Stock levels are being pushed to :channel. This may take a few minutes to complete.', [
                'channel' => $customerSalesChannel->name ?? $customerSalesChannel->reference
            ]),
        ]);
    }
}
