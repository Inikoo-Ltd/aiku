<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 16 Oct 2024 16:40:54 Central Indonesia Time, Office, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use Illuminate\Support\Facades\DB;

class FetchAuroraBackInStockReminder extends FetchAurora
{
    protected function parseModel(): void
    {

        $customer = $this->parseCustomer($this->organisation->id.':'.$this->auroraModelData->{'Back in Stock Reminder Customer Key'});
        if (! $customer or $customer->deleted_at) {
            return;
        }
        $product = $this->parseProduct($this->organisation->id.':'.$this->auroraModelData->{'Back in Stock Reminder Product ID'});
        if (! $product or $product->deleted_at) {
            return;
        }
        $createdAt = $this->parseDatetime($this->auroraModelData->{'Back in Stock Reminder Creation Date'});
        if (! $createdAt) {
            return;
        }

        if ($customer->shop_id != $product->shop_id) {
            return;
        }

        $this->parsedData['customer'] = $customer;
        $this->parsedData['product'] = $product;

        $this->parsedData['back_in_stock_reminder'] = [
            'created_at' => $createdAt,
            'source_id' => $this->organisation->id.':'.$this->auroraModelData->{'Back in Stock Reminder Key'},
            'fetched_at' => now(),
            'last_fetched_at' => now(),
        ];
    }

    protected function fetchData($id): ?object
    {
        return DB::connection('aurora')
            ->table('Back in Stock Reminder Fact')
            ->where('Back in Stock Reminder Key', $id)->first();
    }
}
