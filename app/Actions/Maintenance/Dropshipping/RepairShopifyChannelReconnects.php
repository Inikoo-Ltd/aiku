<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 05 Sep 2026 00:30:00 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Dropshipping;

use App\Actions\Dropshipping\CustomerSalesChannel\Hydrators\CustomerSalesChannelsHydrateCustomerClients;
use App\Actions\Dropshipping\CustomerSalesChannel\Hydrators\CustomerSalesChannelsHydrateOrders;
use App\Actions\Dropshipping\CustomerSalesChannel\Hydrators\CustomerSalesChannelsHydratePortfolios;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairShopifyChannelReconnects
{
    use AsAction;

    /**
     * @return array{portfolios: int, clients: int, orders: int, predecessors: int}
     */
    public function handle(CustomerSalesChannel $keep, bool $dryRun = false): array
    {
        $moved = ['portfolios' => 0, 'clients' => 0, 'orders' => 0, 'predecessors' => 0];

        $predecessors = CustomerSalesChannel::where('customer_id', $keep->customer_id)
            ->where('platform_id', $keep->platform_id)
            ->where('reference', $keep->reference)
            ->where('id', '!=', $keep->id)
            ->where('status', CustomerSalesChannelStatusEnum::CLOSED)
            ->orderBy('id')
            ->get();

        foreach ($predecessors as $old) {
            $moved['predecessors']++;

            $portfolios = $old->portfolios()
                ->whereNotIn('item_id', $keep->portfolios()->select('item_id'))
                ->get();
            $moved['portfolios'] += $portfolios->count();
            $moved['clients']    += $old->clients()->count();
            $moved['orders']     += $old->orders()->count();

            if ($dryRun) {
                continue;
            }

            DB::transaction(function () use ($keep, $old, $portfolios) {
                foreach ($portfolios as $portfolio) {
                    $portfolio->update(['customer_sales_channel_id' => $keep->id, 'status' => true]);
                }

                foreach (['customer_clients', 'orders', 'delivery_notes', 'invoices', 'bundles'] as $table) {
                    DB::table($table)->where('customer_sales_channel_id', $old->id)->update(['customer_sales_channel_id' => $keep->id]);
                }
            });

            $this->hydrate($old);
        }

        if (!$dryRun && $moved['predecessors'] > 0) {
            $this->hydrate($keep);
        }

        return $moved;
    }

    private function hydrate(CustomerSalesChannel $customerSalesChannel): void
    {
        CustomerSalesChannelsHydratePortfolios::run($customerSalesChannel);
        CustomerSalesChannelsHydrateCustomerClients::run($customerSalesChannel);
        CustomerSalesChannelsHydrateOrders::run($customerSalesChannel);
    }

    public function getCommandSignature(): string
    {
        return 'repair:shopify_reconnects {customerSalesChannel? : slug of the open channel to merge into; omitted = every open Shopify channel that is still empty while a closed predecessor holds listings} {--dry-run}';
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();
        $dryRun = (bool) $command->option('dry-run');

        if ($slug = $command->argument('customerSalesChannel')) {
            $channels = CustomerSalesChannel::where('slug', $slug)->get();
        } else {
            $shopify  = Platform::where('type', PlatformTypeEnum::SHOPIFY)->firstOrFail();
            $channels = CustomerSalesChannel::where('platform_id', $shopify->id)
                ->where('status', CustomerSalesChannelStatusEnum::OPEN)
                ->where('number_portfolios', 0)
                ->whereExists(function ($query) {
                    $query->from('customer_sales_channels as old')
                        ->whereColumn('old.customer_id', 'customer_sales_channels.customer_id')
                        ->whereColumn('old.reference', 'customer_sales_channels.reference')
                        ->whereColumn('old.id', '!=', 'customer_sales_channels.id')
                        ->where('old.status', CustomerSalesChannelStatusEnum::CLOSED->value)
                        ->where('old.number_portfolios', '>', 0);
                })
                ->orderBy('id')
                ->get();
        }

        $rows   = [];
        $totals = ['portfolios' => 0, 'clients' => 0, 'orders' => 0];
        foreach ($channels as $channel) {
            $moved = $this->handle($channel, $dryRun);
            if ($moved['predecessors'] === 0) {
                continue;
            }
            $rows[] = [$channel->slug, $moved['predecessors'], $moved['portfolios'], $moved['clients'], $moved['orders']];
            foreach ($totals as $key => $value) {
                $totals[$key] += $moved[$key];
            }
        }

        $command->table(['channel', 'predecessors', 'portfolios', 'clients', 'orders'], $rows);
        $command->info(($dryRun ? 'Would move' : 'Moved')." {$totals['portfolios']} portfolios, {$totals['clients']} clients, {$totals['orders']} orders into ".count($rows).' channels');

        return 0;
    }
}
