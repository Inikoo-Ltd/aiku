<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 05 Sep 2026 12:40:00 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Ebay;

use App\Actions\Dropshipping\CustomerSalesChannel\CloseCustomerSalesChannel;
use App\Actions\Dropshipping\CustomerSalesChannel\Hydrators\CustomerSalesChannelsHydrateCustomerClients;
use App\Actions\Dropshipping\CustomerSalesChannel\Hydrators\CustomerSalesChannelsHydratePortfolios;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class MergeEbayChannelInto
{
    use AsAction;

    /**
     * Moves everything the extra channel holds onto the channel that survives, then closes the extra one
     * through the normal close path so its eBay user row is retired with it.
     *
     * @return array{portfolios: int, clients: int}
     */
    public function handle(CustomerSalesChannel $extra, CustomerSalesChannel $keep, bool $dryRun = false): array
    {
        $portfolios = $extra->portfolios()
            ->whereNotIn('item_id', $keep->portfolios()->select('item_id'))
            ->get();

        $moved = [
            'portfolios' => $portfolios->count(),
            'clients'    => $extra->clients()->count(),
        ];

        if ($dryRun) {
            return $moved;
        }

        DB::transaction(function () use ($keep, $extra, $portfolios) {
            foreach ($portfolios as $portfolio) {
                $portfolio->update(['customer_sales_channel_id' => $keep->id, 'status' => true]);
            }

            foreach (['customer_clients', 'bundles'] as $table) {
                DB::table($table)->where('customer_sales_channel_id', $extra->id)->update(['customer_sales_channel_id' => $keep->id]);
            }
        });

        CloseCustomerSalesChannel::run($extra->refresh());

        CustomerSalesChannelsHydratePortfolios::run($keep);
        CustomerSalesChannelsHydrateCustomerClients::run($keep);

        return $moved;
    }
}
