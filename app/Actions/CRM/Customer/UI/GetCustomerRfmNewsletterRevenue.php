<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 13 Aug 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\Customer\UI;

use App\Enums\CRM\Customer\CustomerRfmSegmentEnum;
use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Revenue of the newsletter traffic source per monetary segment. Each invoice only counts for the
 * share the newsletter touch earned in the customer's attribution, so a customer reached by several
 * channels does not credit the whole of their spend to the newsletter.
 */
class GetCustomerRfmNewsletterRevenue
{
    use AsObject;

    public function handle(Shop $shop, ?Carbon $from, ?Carbon $to): array
    {
        $morphClass = (new Customer())->getMorphClass();

        $query = DB::table('customers as c')
            ->join('model_has_tags as mht', function ($join) use ($morphClass) {
                $join->on('c.id', '=', 'mht.model_id')
                    ->where('mht.model_type', '=', $morphClass);
            })
            ->join('tags as t', 't.id', '=', 'mht.tag_id')
            ->join('model_has_traffic_sources as mhts', function ($join) use ($morphClass) {
                $join->on('c.id', '=', 'mhts.model_id')
                    ->where('mhts.model_type', '=', $morphClass);
            })
            ->join('traffic_sources as ts', function ($join) {
                $join->on('ts.id', '=', 'mhts.traffic_source_id')
                    ->where('ts.type', '=', TrafficSourcesTypeEnum::NEWSLETTER->value);
            })
            ->join('invoices as i', function ($join) {
                $join->on('i.customer_id', '=', 'c.id')
                    ->where('i.in_process', '=', false)
                    ->whereNull('i.deleted_at');
            })
            ->where('c.shop_id', $shop->id)
            ->whereNull('c.deleted_at')
            ->where(DB::raw("t.data->>'type'"), CustomerRfmSegmentEnum::TYPE_MONETARY)
            ->groupBy('t.name')
            ->select('t.name', DB::raw('COALESCE(SUM(i.net_amount * mhts.share), 0) as revenue'));

        if ($from) {
            $query->where('i.date', '>=', $from);
        }

        if ($to) {
            $query->where('i.date', '<=', $to);
        }

        $revenuePerSegment = $query->pluck('revenue', 'name');

        $revenue = [];
        foreach (CustomerRfmSegmentEnum::ofType(CustomerRfmSegmentEnum::TYPE_MONETARY) as $segment) {
            $revenue[$segment->tagName()] = round((float) ($revenuePerSegment[$segment->tagName()] ?? 0), 2);
        }

        return $revenue;
    }
}
