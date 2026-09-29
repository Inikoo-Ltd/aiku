<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026 13:00:00 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Product;

use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Uploads that died because the store ran out of time (full size images before CUS-1745) are queued
 * again, only on open channels that still connect; the per-store funnel in
 * StoreNewProductToCurrentWooCommerce keeps each host at a few creates at once.
 */
class RetryTimedOutWooUploads
{
    use AsAction;

    public string $commandSignature = 'woo:retry-timed-out-uploads {customerSalesChannel? : Slug or id of one channel} {--dispatch : Queue the retries, otherwise only report}';

    public string $commandDescription = 'Queue again the WooCommerce uploads that failed because the store timed out';

    /**
     * @return Collection<int, Portfolio>
     */
    public function handle(?CustomerSalesChannel $customerSalesChannel = null): Collection
    {
        return Portfolio::query()
            ->where('status', true)
            ->whereNull('platform_product_id')
            ->where(function (Builder $query) {
                $query->where('errors_response->message', 'ilike', '%timed out%')
                    ->orWhere('errors_response->message', 'ilike', '%critical error%')
                    ->orWhere('errors_response->message', 'ilike', '%execution time%');
            })
            ->whereHas('customerSalesChannel', function (Builder $query) use ($customerSalesChannel) {
                $query->where('status', 'open')
                    ->where('can_connect_to_platform', true)
                    ->whereHas('platform', fn (Builder $platform) => $platform->where('type', PlatformTypeEnum::WOOCOMMERCE));

                if ($customerSalesChannel) {
                    $query->where('id', $customerSalesChannel->id);
                }
            })
            ->with('customerSalesChannel.user')
            ->get();
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $customerSalesChannel = null;
        if ($channelKey = $command->argument('customerSalesChannel')) {
            $customerSalesChannel = CustomerSalesChannel::where('slug', $channelKey)->orWhere('id', (int) $channelKey)->first();

            if (!$customerSalesChannel) {
                $command->error('No channel has that slug or id');

                return 1;
            }
        }

        $portfolios = $this->handle($customerSalesChannel);

        $command->table(
            ['Channel', 'Store', 'Stuck uploads'],
            $portfolios->groupBy('customer_sales_channel_id')
                ->map(fn (Collection $channelPortfolios) => [
                    $channelPortfolios->first()->customer_sales_channel_id,
                    $channelPortfolios->first()->customerSalesChannel->name,
                    $channelPortfolios->count(),
                ])
                ->sortByDesc(2)
                ->values()
                ->all()
        );

        if (!$command->option('dispatch')) {
            $command->info($portfolios->count().' uploads would be queued, run with --dispatch to queue them');

            return 0;
        }

        $queued = 0;
        foreach ($portfolios as $portfolio) {
            $wooCommerceUser = $portfolio->customerSalesChannel->user;
            if (!$wooCommerceUser instanceof WooCommerceUser) {
                continue;
            }

            StoreNewProductToCurrentWooCommerce::dispatch($wooCommerceUser, $portfolio);
            $queued++;
        }

        $command->info($queued.' uploads queued');

        return 0;
    }
}
