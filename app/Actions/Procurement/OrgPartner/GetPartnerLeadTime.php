<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 1 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgPartner;

use App\Models\Procurement\OrgPartner;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetPartnerLeadTime
{
    use AsObject;

    public const DEFAULT_DAYS = 14;
    public const MIN_SAMPLES  = 5;

    /**
     * Average days from ordering to booked in, measured from this partner's own history.
     * Falls back to an editable per-partner estimate when the history is too thin to trust.
     *
     * @return array{days: int, source: 'measured'|'estimate', samples: int}
     */
    public function handle(OrgPartner $orgPartner): array
    {
        $measured = DB::table('purchase_orders as po')
            ->join('purchase_order_stock_delivery as pivot', 'pivot.purchase_order_id', 'po.id')
            ->join('stock_deliveries as sd', 'sd.id', 'pivot.stock_delivery_id')
            ->where('po.parent_type', 'OrgPartner')
            ->where('po.parent_id', $orgPartner->id)
            ->whereNull('po.deleted_at')
            ->whereNotNull('po.submitted_at')
            ->whereRaw('coalesce(sd.booked_in_at, sd.placed_at) is not null')
            ->where('po.submitted_at', '>=', now()->subYear())
            ->selectRaw('count(*) as samples,
                avg(extract(epoch from coalesce(sd.booked_in_at, sd.placed_at) - po.submitted_at) / 86400) as days')
            ->first();

        if ((int) $measured->samples >= self::MIN_SAMPLES) {
            return [
                'days'    => max(1, (int) round((float) $measured->days)),
                'source'  => 'measured',
                'samples' => (int) $measured->samples,
            ];
        }

        return [
            'days'    => (int) (Arr::get($orgPartner->data, 'shopping.lead_time_days') ?: self::DEFAULT_DAYS),
            'source'  => 'estimate',
            'samples' => (int) $measured->samples,
        ];
    }
}
