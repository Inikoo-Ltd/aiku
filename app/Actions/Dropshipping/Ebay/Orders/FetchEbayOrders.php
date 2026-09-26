<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Jun 2026 12:54:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Ebay\Orders;

use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

class FetchEbayOrders
{
    use AsAction;

    public string $commandSignature = 'fetch:ebay-orders {--active : only channels with recent orders or newly connected}';

    public function asCommand(Command $command): void
    {
        $this->handle((bool) $command->option('active'));
    }

    public function handle(bool $activeChannels = false): void
    {
        $platform = Platform::where('type', PlatformTypeEnum::EBAY)->first();

        $customerSalesChannels = CustomerSalesChannel::where('platform_id', $platform->id)
            ->where('status', CustomerSalesChannelStatusEnum::OPEN)
            ->where('platform_status', true)
            ->when(
                $activeChannels,
                fn (Builder $query) => $query->where(
                    fn (Builder $query) => $query->where('last_order_created_at', '>', now()->subDays(30))
                        ->orWhere('created_at', '>', now()->subDays(7))
                ),
                fn (Builder $query) => $query->where(
                    fn (Builder $query) => $query->whereNull('last_order_created_at')
                        ->orWhere('last_order_created_at', '<=', now()->subDays(30))
                )->where('created_at', '<=', now()->subDays(7))
            )
            ->get();

        $maxDelaySeconds = $activeChannels ? 300 : 3600;

        /** @var CustomerSalesChannel $customerSalesChannel */
        foreach ($customerSalesChannels as $customerSalesChannel) {
            if ($customerSalesChannel->user) {
                FetchEbayUserOrders::dispatch($customerSalesChannel->user)->delay(now()->addSeconds(rand(1, $maxDelaySeconds)));
            }
        }
    }
}
