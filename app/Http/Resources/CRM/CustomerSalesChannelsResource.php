<?php

/*
 * author Arya Permana - Kirin
 * created on 11-04-2025-09h-29m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\CRM;

use App\Actions\Retina\UI\Layout\GetPlatformLogo;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $type
 * @property mixed $slug
 * @property mixed $number_portfolios
 * @property mixed $number_customer_clients
 * @property mixed $number_orders
 * @property mixed $platform_id
 * @property mixed $reference
 * @property mixed $id
 */
class CustomerSalesChannelsResource extends JsonResource
{
    use GetPlatformLogo;
    public function toArray($request): array
    {
        /** @var Platform $platform */
        $platform = Platform::find($this->platform_id);

        $customerSalesChannels = CustomerSalesChannel::find($this->id);
        $status = 'connected';
        if ($customerSalesChannels->user instanceof ShopifyUser) {
            $settings = $customerSalesChannels->user->settings ?? [];
            if (empty($settings) && empty($settings['webhook'])) {
                $status = 'not-connected';
            }
        } elseif ($customerSalesChannels->user instanceof WooCommerceUser) {
            $settings = $customerSalesChannels->user->settings ?? [];

            if (empty($settings['credentials']) or empty($settings['webhooks'])) {
                $status = 'not-connected';
            }
        }

        return [
            'slug'              => $this->slug,
            'id'                => $this->id,
            'reference'         => $this->reference,
            'number_portfolios' => $this->number_portfolios,
            'number_clients'    => $this->number_customer_clients,
            'number_orders'     => $this->number_orders,
            'type'              => $this->type,
            'status'              => $this->status,
            'amount'            => $this->total_amount,
            'platform_code'     => $platform?->code,
            'platform_name'     => $platform?->name,
            'platform_image'    => $this->getPlatformLogo($customerSalesChannels),
            'name'              => $this->name ?? $this->reference,
            'connection'        => $status,
            'unlink_route' => [
                'method' => 'delete',
                'name' => 'retina.models.customer_sales_channel.unlink',
                'parameters' => [
                    'customerSalesChannel' => $this->id
                ]
            ],
            'toggle_route' => [
                'method' => 'patch',
                'name' => 'retina.models.customer_sales_channel.toggle',
                'parameters' => [
                    'customerSalesChannel' => $this->id
                ]
            ]
        ];
    }
}
