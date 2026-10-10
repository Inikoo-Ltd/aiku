<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026 13:00:00 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Product;

use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Enums\Ordering\PlatformLogs\PlatformPortfolioLogsTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Uploads that died because the store ran out of time (full size images before CUS-1745, or the
 * host's gateway giving up with a 504) are queued again, only on open channels that still connect;
 * a product the store created anyway is linked by its sku instead of duplicated. The per-store funnel in
 * StoreNewProductToCurrentWooCommerce keeps each host at a few creates at once.
 *
 * The nightly run only takes uploads tried in the last few days and gives up after a few attempts:
 * an upload the customer asked for months ago must not suddenly appear in their store, and a store
 * that times out every time is left alone with the timeout message on the product. Once it has given
 * up, the upload is only looked up in the store, in case the last attempt created it after all.
 */
class RetryTimedOutWooUploads
{
    use AsAction;

    public string $commandSignature = 'woo:retry-timed-out-uploads {customerSalesChannel? : Slug or id of one channel} {--dispatch : Queue the retries, otherwise only report} {--days= : Only uploads last tried within this many days} {--max-attempts= : Skip uploads already tried this many times}';

    public string $commandDescription = 'Queue again the WooCommerce uploads that failed because the store timed out';

    private const string UPLOAD_ATTEMPTS = '(select count(*) from platform_portfolio_logs where platform_portfolio_logs.portfolio_id = portfolios.id and platform_portfolio_logs.type = ?)';

    /**
     * @return Collection<int, Portfolio>
     */
    public function handle(?CustomerSalesChannel $customerSalesChannel = null, ?int $withinDays = null, ?int $maxAttempts = null): Collection
    {
        return $this->timedOutUploads($customerSalesChannel, $withinDays)
            ->when($maxAttempts, fn (Builder $query) => $query->whereRaw(
                self::UPLOAD_ATTEMPTS.' < ?',
                [PlatformPortfolioLogsTypeEnum::UPLOAD->value, $maxAttempts]
            ))
            ->get();
    }

    /**
     * The uploads the nightly run no longer retries. The last attempt can time out and still leave
     * the product in the store, and no later attempt is there to find it.
     *
     * @return Collection<int, Portfolio>
     */
    public function givenUp(?CustomerSalesChannel $customerSalesChannel, int $withinDays, int $maxAttempts): Collection
    {
        return $this->timedOutUploads($customerSalesChannel, $withinDays)
            ->whereRaw(self::UPLOAD_ATTEMPTS.' >= ?', [PlatformPortfolioLogsTypeEnum::UPLOAD->value, $maxAttempts])
            ->get();
    }

    private function timedOutUploads(?CustomerSalesChannel $customerSalesChannel, ?int $withinDays): Builder
    {
        return Portfolio::query()
            ->where('status', true)
            ->whereNull('platform_product_id')
            ->when($withinDays, fn (Builder $query) => $query->whereExists(
                fn ($logs) => $logs->selectRaw('1')
                    ->from('platform_portfolio_logs')
                    ->whereColumn('platform_portfolio_logs.portfolio_id', 'portfolios.id')
                    ->where('platform_portfolio_logs.type', PlatformPortfolioLogsTypeEnum::UPLOAD->value)
                    ->where('platform_portfolio_logs.created_at', '>=', now()->subDays($withinDays))
            ))
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
            ->with('customerSalesChannel.user');
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

        $withinDays  = $command->option('days') !== null ? (int) $command->option('days') : null;
        $maxAttempts = $command->option('max-attempts') !== null ? (int) $command->option('max-attempts') : null;

        $portfolios = $this->handle($customerSalesChannel, $withinDays, $maxAttempts);
        $givenUp    = $withinDays && $maxAttempts ? $this->givenUp($customerSalesChannel, $withinDays, $maxAttempts) : collect();

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
            $command->info($givenUp->count().' uploads no longer retried would be looked up in their store');

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

        $givenUp->each(fn (Portfolio $portfolio) => LinkTimedOutWooUpload::dispatch($portfolio));
        $command->info($givenUp->count().' uploads no longer retried are looked up in their store');

        return 0;
    }
}
