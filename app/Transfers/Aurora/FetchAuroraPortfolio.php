<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 11 Jun 2024 16:02:25 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\Platform;
use Illuminate\Support\Facades\DB;

class FetchAuroraPortfolio extends FetchAurora
{
    protected function parseModel(): void
    {
        $customer = $this->parseCustomer($this->organisation->id.':'.$this->auroraModelData->{'Customer Portfolio Customer Key'});
        if (!$customer) {
            return;
        }

        if ($customer->trashed()) {
            return;
        }

        /** @var Product $product */
        $product = $this->parseProduct($this->organisation->id.':'.$this->auroraModelData->{'Customer Portfolio Product ID'});
        if (!$product) {
            return;
        }

        if ($product->trashed()) {
            return;
        }


        $this->parsedData['customer'] = $customer;

        $platform = Platform::where('type', PlatformTypeEnum::MANUAL)->first();

        $this->parsedData['portfolio'] = [
            'platform_id'     => $platform->id,
            'shop_id'         => $customer->shop_id,
            'product_id'      => $product->id,
            'source_id'       => $this->organisation->id.':'.$this->auroraModelData->{'Customer Portfolio Key'},
            'reference'       => $this->auroraModelData->{'Customer Portfolio Reference'},
            'created_at'      => $this->auroraModelData->{'Customer Portfolio Creation Date'},
            'last_added_at'   => $this->auroraModelData->{'Customer Portfolio Creation Date'},
            'status'          => $this->auroraModelData->{'Customer Portfolio Customers State'} === 'Active',
            'fetched_at'      => now(),
            'last_fetched_at' => now(),
        ];
        $lastRemoved                   = $this->auroraModelData->{'Customer Portfolio Removed Date'};
        if ($lastRemoved) {
            $this->parsedData['portfolio']['last_removed_at'] = $lastRemoved;
        }
    }


    protected function fetchData($id): object|null
    {
        return DB::connection('aurora')
            ->table('Customer Portfolio Fact')
            ->where('Customer Portfolio Key', $id)->first();
    }
}
