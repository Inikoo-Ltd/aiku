<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 13 Aug 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\Customer;

use App\Models\CRM\CustomerRfmSnapshot;
use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsObject;

class StoreCustomerRfmSnapshot
{
    use AsObject;

    public function handle(int $shopId, array $tagsSummary, Carbon $snapshotDate): CustomerRfmSnapshot
    {
        $snapshot = CustomerRfmSnapshot::where('shop_id', $shopId)
            ->whereDate('snapshot_date', $snapshotDate->toDateString())
            ->first();

        if ($snapshot) {
            $snapshot->update(['tags_summary' => $tagsSummary]);

            return $snapshot;
        }

        return CustomerRfmSnapshot::create([
            'shop_id'       => $shopId,
            'tags_summary'  => $tagsSummary,
            'snapshot_date' => $snapshotDate,
        ]);
    }
}
