<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Fri, 28 Nov 2025 16:28:36 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Dropshipping\Platform\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateSalesMetrics;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\PlatformSalesMetrics;
use App\Models\Dropshipping\Portfolio;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class PlatformHydrateSalesMetrics implements ShouldBeUnique
{
    use AsAction;
    use WithHydrateSalesMetrics;

    public string $commandSignature = 'hydrate:platform-sales-metrics {platform}';

    public function getJobUniqueId(Platform $platform, Carbon $date): string
    {
        return $platform->id . '-' . $date->format('YmdHis');
    }

    public function asCommand(Command $command): void
    {
        $platform = Platform::where('slug', $command->argument('platform'))->first();

        if (!$platform) {
            return;
        }

        $today = Carbon::today();

        $this->handle($platform, $today);
    }

    public function handle(Platform $platform, Carbon $date): void
    {
        $dayStart = $date->copy()->startOfDay();
        $dayEnd   = $date->copy()->endOfDay();

        $metrics = $this->getSalesMetrics([
            'context' => ['platform_id' => $platform->id],
            'start'   => $dayStart,
            'end'     => $dayEnd,
            'fields'  => [
                'invoices',
                'sales_grp_currency'
            ]
        ]);

        $metrics = array_merge(
            $metrics,
            [
                'new_channels' => CustomerSalesChannel::where('platform_id', $platform->id)
                    ->where('status', CustomerSalesChannelStatusEnum::OPEN)
                    ->whereBetween('created_at', [$dayStart, $dayEnd])
                    ->count(),

                'new_customers' => CustomerSalesChannel::leftJoin('customers', 'customer_sales_channels.customer_id', '=', 'customers.id')
                    ->where('platform_id', $platform->id)
                    ->whereBetween('customer_sales_channels.created_at', [$dayStart, $dayEnd])
                    ->distinct('customer_sales_channels.customer_id')
                    ->count('customer_sales_channels.customer_id'),

                'new_portfolios' => Portfolio::where('item_type', class_basename(Product::class))
                    ->leftJoin('products', 'portfolios.item_id', '=', 'products.id')
                    ->where('platform_id', $platform->id)
                    ->whereBetween('portfolios.created_at', [$dayStart, $dayEnd])
                    ->distinct('portfolios.item_id')
                    ->count('portfolios.item_id'),

                'new_customer_client' => CustomerClient::where('platform_id', $platform->id)
                    ->whereBetween('created_at', [$dayStart, $dayEnd])
                    ->count(),
            ]
        );

        PlatformSalesMetrics::updateOrCreate(
            [
                'platform_id' => $platform->id,
                'date'        => $dayStart
            ],
            $metrics
        );
    }
}
