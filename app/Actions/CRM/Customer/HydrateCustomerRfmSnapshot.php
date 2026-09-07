<?php

namespace App\Actions\CRM\Customer;

use App\Enums\CRM\Customer\CustomerRfmSegmentEnum;
use App\Models\CRM\Customer;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class HydrateCustomerRfmSnapshot
{
    use AsAction;

    public string $commandSignature = 'hydrate:customer-rfm-snapshot';

    public function handle(): void
    {
        $now        = now();
        $morphClass = (new Customer())->getMorphClass();

        $results = DB::table('customers as c')
            ->join('model_has_tags as mht', function ($join) use ($morphClass) {
                $join->on('c.id', '=', 'mht.model_id')
                    ->where('mht.model_type', '=', $morphClass);
            })
            ->join('tags as t', 't.id', '=', 'mht.tag_id')
            ->whereNull('c.deleted_at')
            ->whereIn(DB::raw("t.data->>'type'"), CustomerRfmSegmentEnum::types())
            ->select('c.shop_id', 't.name as tag_name', DB::raw('count(distinct c.id) as customer_count'))
            ->groupBy('c.shop_id', 't.name')
            ->get();

        $shops = [];
        foreach ($results as $row) {
            $shops[$row->shop_id][$row->tag_name] = $row->customer_count;
        }

        foreach ($shops as $shopId => $summary) {
            StoreCustomerRfmSnapshot::run($shopId, $summary, $now);
        }
    }
}
