<?php

/*
 * author Arya Permana - Kirin
 * created on 16-05-2025-16h-10m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\Dropshipping\Ebay\Orders\Webhooks\CatchRetinaOrdersFromEbay;
use App\Actions\Dropshipping\Magento\Orders\GetRetinaOrdersFromMagento;
use App\Actions\Dropshipping\WooCommerce\Orders\Webhooks\CatchRetinaOrdersFromWooCommerce;
use App\Actions\RetinaAction;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\EbayUser;
use App\Models\Dropshipping\MagentoUser;
use App\Models\Dropshipping\WooCommerceUser;
use Lorisleiva\Actions\Concerns\AsCommand;

class ScheduleGetRetinaOrders extends RetinaAction
{
    use AsCommand;

    public string $commandSignature = 'schedule:platform-orders';

    public function asCommand()
    {
        $this->handle();
    }

    public function handle(): void
    {
        CustomerSalesChannel::whereIn('platform_user_type', [
            class_basename(WooCommerceUser::class),
            class_basename(EbayUser::class),
            class_basename(MagentoUser::class)
        ])->orderBy('customer_sales_channels.id')
            ->chunk(100, function ($channels) {
                foreach ($channels as $channel) {
                    if ($user = $channel->user) {
                        match ($channel->platform->type) {
                            PlatformTypeEnum::WOOCOMMERCE => CatchRetinaOrdersFromWooCommerce::dispatch($user),
                            PlatformTypeEnum::EBAY => CatchRetinaOrdersFromEbay::dispatch($user),
                            PlatformTypeEnum::MAGENTO => GetRetinaOrdersFromMagento::dispatch($user),
                            default => null
                        };
                    }
                }
            });
    }
}
