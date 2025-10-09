<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Product;

use App\Actions\OrgAction;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreBulkNewProductToCurrentWooCommerce extends OrgAction
{
    use AsAction;
    use WithAttributes;

    /**
     * @throws \Exception
     */
    public function handle(CustomerSalesChannel $customerSalesChannel, array $attributes): void
    {
        $portfolios = $customerSalesChannel
            ->portfolios()
            ->where('status', true)
            ->whereIn('id', Arr::get($attributes, 'portfolios'))
            ->get();

        foreach ($portfolios as $portfolio) {
            StoreNewProductToCurrentWooCommerce::dispatch($customerSalesChannel->user, $portfolio);
        }
    }
}
