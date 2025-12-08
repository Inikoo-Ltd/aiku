<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:09 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use Illuminate\Support\Facades\DB;

class FetchAuroraFavourite extends FetchAurora
{
    protected function parseModel(): void
    {
        $customer = $this->parseCustomer($this->organisation->id.':'.$this->auroraModelData->{'Customer Favourite Product Customer Key'});
        if (! $customer or $customer->deleted_at) {
            return;
        }
        $product = $this->parseProduct($this->organisation->id.':'.$this->auroraModelData->{'Customer Favourite Product Product ID'});
        if (! $product or $product->deleted_at) {
            return;
        }
        $createdAt = $this->parseDatetime($this->auroraModelData->{'Customer Favourite Product Creation Date'});
        if (! $createdAt) {
            return;
        }

        if ($customer->shop_id != $product->shop_id) {
            return;
        }

        $this->parsedData['customer'] = $customer;
        $this->parsedData['product'] = $product;

        $this->parsedData['favourite'] = [
            'created_at' => $createdAt,
            'source_id' => $this->organisation->id.':'.$this->auroraModelData->{'Customer Favourite Product Key'},
            'fetched_at' => now(),
            'last_fetched_at' => now(),
            'unfavourited_at' => null,
        ];
    }

    protected function fetchData($id): ?object
    {
        return DB::connection('aurora')
            ->table('Customer Favourite Product Fact')
            ->where('Customer Favourite Product Key', $id)->first();
    }
}
