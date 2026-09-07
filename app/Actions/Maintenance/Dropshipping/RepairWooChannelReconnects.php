<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 06 Sep 2026 09:00:00 British Summer Time, Sheffield, UK
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Dropshipping;

use App\Actions\Dropshipping\CustomerSalesChannel\CloseCustomerSalesChannel;
use App\Actions\Dropshipping\CustomerSalesChannel\Hydrators\CustomerSalesChannelsHydrateCustomerClients;
use App\Actions\Dropshipping\CustomerSalesChannel\Hydrators\CustomerSalesChannelsHydrateOrders;
use App\Actions\Dropshipping\CustomerSalesChannel\Hydrators\CustomerSalesChannelsHydratePortfolios;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Every authorisation of a WooCommerce store used to mint a new channel, so one store ended up
 * with a trail of closed channels still holding portfolios and orders, and sometimes a second
 * open channel that never got anything. This folds the trail back into the channel the customer
 * is using: closed predecessors are merged into it and empty open duplicates are closed.
 */
class RepairWooChannelReconnects
{
    use AsAction;

    private const array CHANNEL_TABLES = [
        'customer_clients',
        'orders',
        'delivery_notes',
        'invoices',
        'returns',
        'pallet_returns',
        'bundles',
        'platform_portfolio_logs',
        'download_portfolio_customer_sales_channel',
    ];

    /**
     * An open duplicate is only closed while the kept channel can still reach the store, so the
     * keys the customer most recently issued are never thrown away in favour of dead ones.
     *
     * @return array{portfolios: int, clients: int, orders: int, predecessors: int, closed_duplicates: int, skipped: int}
     */
    public function handle(CustomerSalesChannel $keep, bool $dryRun = false): array
    {
        $moved = ['portfolios' => 0, 'clients' => 0, 'orders' => 0, 'predecessors' => 0, 'closed_duplicates' => 0, 'skipped' => 0];

        $keepCanConnect = null;

        foreach ($this->siblings($keep) as $sibling) {
            if ($sibling->status == CustomerSalesChannelStatusEnum::OPEN) {
                $keepCanConnect ??= (bool) $keep->user?->checkConnection();

                if (!$keepCanConnect || $sibling->portfolios()->exists() || $sibling->orders()->exists() || $sibling->clients()->exists()) {
                    $moved['skipped']++;

                    continue;
                }

                $moved['closed_duplicates']++;

                if (!$dryRun) {
                    CloseCustomerSalesChannel::make()->handle($sibling);
                }

                continue;
            }

            $moved['predecessors']++;

            $portfolios = $sibling->portfolios()
                ->whereNotNull('item_id')
                ->whereNotIn('item_id', $keep->portfolios()->whereNotNull('item_id')->select('item_id'))
                ->get();
            $moved['portfolios'] += $portfolios->count();
            $moved['clients']    += $sibling->clients()->count();
            $moved['orders']     += $sibling->orders()->count();

            if ($dryRun) {
                continue;
            }

            DB::transaction(function () use ($keep, $sibling, $portfolios) {
                foreach ($portfolios as $portfolio) {
                    $portfolio->update(['customer_sales_channel_id' => $keep->id, 'status' => true]);
                }

                foreach (self::CHANNEL_TABLES as $table) {
                    DB::table($table)->where('customer_sales_channel_id', $sibling->id)->update(['customer_sales_channel_id' => $keep->id]);
                }
            });

            $this->hydrate($sibling);
        }

        if (!$dryRun && $moved['predecessors'] > 0) {
            $this->hydrate($keep);
        }

        return $moved;
    }

    public static function storeKey(?string $storeUrl): string
    {
        return Str::lower(rtrim((string) $storeUrl, '/'));
    }

    /**
     * Other channels of the same customer whose WooCommerce user points at the same store,
     * open ones first so an empty duplicate is closed before anything is merged.
     *
     * @return Collection<int, CustomerSalesChannel>
     */
    private function siblings(CustomerSalesChannel $keep): Collection
    {
        $wooCommerceUser = WooCommerceUser::withTrashed()->where('customer_sales_channel_id', $keep->id)->first();

        if (!$wooCommerceUser || blank($wooCommerceUser->store_url)) {
            return collect();
        }

        $channelIds = WooCommerceUser::withTrashed()
            ->where('customer_id', $keep->customer_id)
            ->where('customer_sales_channel_id', '!=', $keep->id)
            ->whereRaw("lower(rtrim(store_url, '/')) = ?", [self::storeKey($wooCommerceUser->store_url)])
            ->pluck('customer_sales_channel_id');

        return CustomerSalesChannel::whereIn('id', $channelIds)
            ->where('platform_id', $keep->platform_id)
            ->whereIn('status', [CustomerSalesChannelStatusEnum::OPEN, CustomerSalesChannelStatusEnum::CLOSED])
            ->orderByRaw("status = 'open' desc")
            ->orderBy('id')
            ->get();
    }

    private function hydrate(CustomerSalesChannel $customerSalesChannel): void
    {
        CustomerSalesChannelsHydratePortfolios::run($customerSalesChannel);
        CustomerSalesChannelsHydrateCustomerClients::run($customerSalesChannel);
        CustomerSalesChannelsHydrateOrders::run($customerSalesChannel);
    }

    /**
     * One open channel per customer and store: the one carrying listings or orders, the oldest when none does.
     *
     * @return Collection<int, CustomerSalesChannel>
     */
    public static function channelsToKeep(): Collection
    {
        $groups = WooCommerceUser::withTrashed()
            ->join('customer_sales_channels', 'customer_sales_channels.id', '=', 'woo_commerce_users.customer_sales_channel_id')
            ->whereNotNull('woo_commerce_users.store_url')
            ->selectRaw("woo_commerce_users.customer_id, lower(rtrim(woo_commerce_users.store_url, '/')) as store, count(*) as channels")
            ->groupBy('woo_commerce_users.customer_id', 'store')
            ->havingRaw('count(*) > 1')
            ->get();

        $keeps = collect();

        foreach ($groups as $group) {
            $keep = CustomerSalesChannel::query()
                ->join('woo_commerce_users', 'woo_commerce_users.customer_sales_channel_id', '=', 'customer_sales_channels.id')
                ->where('woo_commerce_users.customer_id', $group->customer_id)
                ->whereRaw("lower(rtrim(woo_commerce_users.store_url, '/')) = ?", [$group->store])
                ->whereNull('woo_commerce_users.deleted_at')
                ->where('customer_sales_channels.status', CustomerSalesChannelStatusEnum::OPEN)
                ->orderByRaw('customer_sales_channels.number_portfolios + customer_sales_channels.number_orders desc')
                ->orderBy('customer_sales_channels.id')
                ->select('customer_sales_channels.*')
                ->first();

            if ($keep) {
                $keeps->push($keep);
            }
        }

        return $keeps;
    }

    public function getCommandSignature(): string
    {
        return 'repair:woo_reconnects {customerSalesChannel? : slug of the open channel to merge into; omitted = every store that has more than one channel for the same customer} {--dry-run}';
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();
        $dryRun = (bool) $command->option('dry-run');

        if ($slug = $command->argument('customerSalesChannel')) {
            $channels = CustomerSalesChannel::where('slug', $slug)
                ->where('status', CustomerSalesChannelStatusEnum::OPEN)
                ->whereHasMorph('user', WooCommerceUser::class)
                ->get();

            if ($channels->isEmpty()) {
                $command->error('Not an open WooCommerce channel with a live user: '.$slug);

                return 1;
            }
        } else {
            $channels = self::channelsToKeep();
        }

        $rows   = [];
        $totals = ['portfolios' => 0, 'clients' => 0, 'orders' => 0, 'predecessors' => 0, 'closed_duplicates' => 0, 'skipped' => 0];
        foreach ($channels as $channel) {
            $moved = $this->handle($channel, $dryRun);
            if ($moved['predecessors'] === 0 && $moved['closed_duplicates'] === 0 && $moved['skipped'] === 0) {
                continue;
            }
            $rows[] = [$channel->slug, $moved['predecessors'], $moved['portfolios'], $moved['clients'], $moved['orders'], $moved['closed_duplicates'], $moved['skipped']];
            foreach ($totals as $key => $value) {
                $totals[$key] += $moved[$key];
            }
        }

        $command->table(['channel', 'predecessors', 'portfolios', 'clients', 'orders', 'empty duplicates closed', 'open duplicates skipped'], $rows);
        $command->info(($dryRun ? 'Would move' : 'Moved')." {$totals['portfolios']} portfolios, {$totals['clients']} clients, {$totals['orders']} orders from {$totals['predecessors']} closed channels into ".count($rows).' channels, '.($dryRun ? 'would close' : 'closed')." {$totals['closed_duplicates']} empty duplicates, skipped {$totals['skipped']} open duplicates that hold data");

        return 0;
    }
}
