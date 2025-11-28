<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 13 Jul 2025 21:45:44 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\CRM;

use App\Actions\Retina\UI\Layout\GetPlatformLogo;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Helpers\TaxCategory;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * @property mixed $type
 * @property mixed $slug
 * @property mixed $number_portfolios
 * @property mixed $number_customer_clients
 * @property mixed $number_orders
 * @property mixed $platform_id
 * @property mixed $reference
 * @property mixed $id
 * @property mixed $name
 * @property mixed $status
 * @property mixed $total_amount
 * @property mixed $platform_code
 * @property mixed $platform_name
 * @property mixed $ban_stock_update_util
 * @property mixed $settings
 */
class RetinaCustomerSalesChannelResource extends JsonResource
{
    use GetPlatformLogo;

    public function toArray($request): array
    {
        /** @var CustomerSalesChannel $customerSalesChannels */
        $customerSalesChannels = $this;

        $reconnectRoute = null;
        $testRoute      = null;
        $siteUrl      = null;

        if (in_array($customerSalesChannels->platform->type, [
            PlatformTypeEnum::SHOPIFY,
            PlatformTypeEnum::WOOCOMMERCE,
            PlatformTypeEnum::MAGENTO,
            PlatformTypeEnum::EBAY,

        ])) {
            $reconnectRoute = [
                'name'       => 'retina.dropshipping.customer_sales_channels.reconnect',
                'parameters' => [
                    'customerSalesChannel' => $this->slug
                ],
                'method'     => 'get',
            ];
        }

        if ($customerSalesChannels->platform->type == PlatformTypeEnum::WOOCOMMERCE) {
            $testRoute = [
                'name'       => 'retina.dropshipping.platform.wc.test_connection',
                'parameters' => [
                    'customerSalesChannel' => $this->slug
                ],
                'method'     => 'post',
            ];

            /** @var \App\Models\Dropshipping\WooCommerceUser $wooUser */
            $wooUser = $customerSalesChannels->user;

            $siteUrl = Arr::get($wooUser->settings, 'credentials.store_url');
        }

        $taxCategory = null;
        if (Arr::get($this->settings, 'tax_category.checked')) {
            $taxCategory = TaxCategory::find(Arr::get($this->settings, 'tax_category.id'));
        }

        return [
            'slug'                    => $this->slug,
            'id'                      => $this->id,
            'reference'               => $this->reference,
            'name'                    => $this->name ?? $this->reference,
            'number_portfolios'       => $this->number_portfolios,
            'number_customer_clients' => $this->number_customer_clients,
            'number_orders'           => $this->number_orders,
            'type'                    => $customerSalesChannels->platform->type,
            'status'                  => $this->status,
            'amount'                  => $this->total_amount,
            'platform_code'           => $this->platform_code,
            'platform_name'           => $this->platform_name,
            'platform_image'          => $this->getPlatformLogo($customerSalesChannels->platform->code),

            'ban_stock_update_until' => $this->ban_stock_update_util,
            'include_vat'            => Arr::get($this->settings, 'tax_category.checked'),
            'vat_rate'               => $taxCategory?->rate,
            'store_url' => $siteUrl,
            'reconnect_route' => $reconnectRoute,
            'test_route'      => $testRoute,
            'delete_route'    => [
                'method'     => 'delete',
                'name'       => 'retina.models.customer_sales_channel.delete',
                'parameters' => [
                    'customerSalesChannel' => $this->id
                ]
            ],

        ];
    }
}
