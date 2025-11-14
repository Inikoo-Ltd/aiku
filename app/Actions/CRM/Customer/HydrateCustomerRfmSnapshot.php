<?php

namespace App\Actions\CRM\Customer;

use App\Models\CRM\CustomerRfmSnapshot;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class HydrateCustomerRfmSnapshot
{
    use AsAction;

    public string $commandSignature = 'hydrate:customer-rfm-snapshot';

    public function handle(): void
    {
        $now = now();
        $today = $now->toDateString();

        $results = DB::table('customers as c')
            ->join('model_has_tags as mht', function ($join) {
                $join->on('c.id', '=', 'mht.model_id')
                    ->where('mht.model_type', '=', 'Customer');
            })
            ->join('tags as t', 't.id', '=', 'mht.tag_id')
            ->whereIn(DB::raw("t.data->>'type'"), ['recency','frequency','monetary'])
            ->select('c.shop_id', 't.name as tag_name', DB::raw('count(distinct c.id) as customer_count'))
            ->groupBy('c.shop_id', 't.name')
            ->get();

        $shops = [];
        foreach ($results as $row) {
            $shops[$row->shop_id][$row->tag_name] = $row->customer_count;
        }

        foreach ($shops as $shopId => $summary) {
            $exists = CustomerRfmSnapshot::where('shop_id', $shopId)
                ->whereDate('snapshot_date', $today)
                ->exists();

            if (!$exists) {
                CustomerRfmSnapshot::create([
                    'shop_id' => $shopId,
                    'tags_summary' => $summary,
                    'snapshot_date' => $now,
                ]);
            }
        }
    }
}
