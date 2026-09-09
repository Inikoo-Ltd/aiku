<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 08 Sep 2026 18:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\ProductionOutput;

use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Models\Inventory\Warehouse;
use App\Models\Procurement\OrgPartner;
use App\Models\Production\JobOrder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class GetFinishedProductionJobOrders
{
    use AsAction;

    /**
     * Job orders the artisans have finished that the warehouse still has to put somewhere.
     * Destination is derived from the to-produce lines behind the job order: a partner's
     * gathering location when every line is for that partner, otherwise normal stock.
     *
     * @return array<int, array<string, mixed>>
     */
    public function handle(Warehouse $warehouse): array
    {
        $partners = OrgPartner::where('organisation_id', $warehouse->organisation_id)
            ->whereNotNull('goods_out_location_id')
            ->with(['partner', 'goodsOutLocation'])
            ->get()
            ->keyBy('partner_id');

        $jobOrders = JobOrder::where('organisation_id', $warehouse->organisation_id)
            ->where('state', JobOrderStateEnum::CONFIRMED)
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('job_order_item_tasks')->whereColumn('job_order_item_tasks.job_order_id', 'job_orders.id'))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('job_order_item_tasks')->whereColumn('job_order_item_tasks.job_order_id', 'job_orders.id')->where('job_order_item_tasks.state', '!=', 'done'))
            ->with(['employee', 'jobOrderItems.artefact', 'jobOrderItems.tasks'])
            ->orderBy('confirmed_at')
            ->get();

        $buyersByJobOrder = DB::table('partner_shopping_list_items')
            ->whereIn('job_order_id', $jobOrders->pluck('id'))
            ->whereNull('deleted_at')
            ->get(['job_order_id', 'organisation_id', 'partner_organisation_id'])
            ->groupBy('job_order_id');

        return $jobOrders->map(function (JobOrder $jobOrder) use ($partners, $buyersByJobOrder) {
            $lines    = $buyersByJobOrder->get($jobOrder->id, collect());
            $buyerIds = $lines->whereNotNull('partner_organisation_id')->pluck('organisation_id')->unique();
            $partner  = $buyerIds->count() === 1 && $lines->count() === $lines->whereNotNull('partner_organisation_id')->count()
                ? $partners->get($buyerIds->first())
                : null;

            return [
                'id'           => $jobOrder->id,
                'reference'    => $jobOrder->reference,
                'artisan'      => $jobOrder->employee?->contact_name,
                'finished_at'  => $jobOrder->jobOrderItems->flatMap->tasks->max('updated_at')?->toISOString(),
                'items'        => $jobOrder->jobOrderItems->map(fn ($item) => [
                    'code'     => $item->artefact->code,
                    'name'     => $item->artefact->name,
                    'quantity' => (float) $item->tasks->sortByDesc('position')->first()?->quantity_made,
                ])->values()->all(),
                'destination'  => $partner
                    ? ['type' => 'partner', 'label' => $partner->partner->code, 'location_code' => $partner->goodsOutLocation->code]
                    : ['type' => 'stock', 'label' => __('Stock'), 'location_code' => null],
            ];
        })->all();
    }
}
