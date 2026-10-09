<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 9 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgPartner;

use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\PartnerShoppingListItem;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Where the lines a buyer submitted to a hub stand on the hub's production board, with the same
 * rules as IndexPartnerShippingList::getBoardLanes.
 */
class GetPartnerProductionLanes
{
    use AsObject;

    public const array LANES = ['backlog', 'preparing', 'assigned', 'producing'];

    /**
     * Done lines are left out: the Orders keep growing, so a done count would only ever go up.
     *
     * @return array{backlog: int, preparing: int, assigned: int, producing: int}
     */
    public function handle(OrgPartner $orgPartner): array
    {
        $byLane = $this->lanesQuery($orgPartner)
            ->groupBy('lane')
            ->selectRaw('count(*) as total')
            ->pluck('total', 'lane');

        return collect(self::LANES)
            ->mapWithKeys(fn (string $lane) => [$lane => (int) ($byLane[$lane] ?? 0)])
            ->all();
    }

    /**
     * @param  array<int, int>  $lineIds
     * @return array<int, string>  lane keyed by line id, lines the hub does not make are left out
     */
    public function ofLines(OrgPartner $orgPartner, array $lineIds): array
    {
        if (!$lineIds) {
            return [];
        }

        return $this->lanesQuery($orgPartner)
            ->whereIn('partner_shopping_list_items.id', $lineIds)
            ->addSelect('partner_shopping_list_items.id')
            ->pluck('lane', 'id')
            ->all();
    }

    private function lanesQuery(OrgPartner $orgPartner): Builder
    {
        $taskStatesSql = 'from job_order_item_tasks
            join job_order_items on job_order_items.id = job_order_item_tasks.job_order_item_id
            join artefacts as task_artefacts on task_artefacts.id = job_order_items.artefact_id
            where job_order_items.job_order_id = job_orders.id and task_artefacts.org_stock_id = org_stocks.id';

        $query = DB::table('partner_shopping_list_items')
            ->join('org_stocks', function ($join) use ($orgPartner) {
                $join->on('org_stocks.stock_id', 'partner_shopping_list_items.stock_id')
                    ->where('org_stocks.organisation_id', $orgPartner->partner_id);
            })
            ->leftJoin('job_orders', 'job_orders.id', 'partner_shopping_list_items.job_order_id')
            ->where('partner_shopping_list_items.org_partner_id', $orgPartner->id)
            ->where('partner_shopping_list_items.state', ShoppingListItemStateEnum::OPEN)
            ->whereNull('partner_shopping_list_items.deleted_at')
            ->whereExists(function ($query) {
                $query->from('artefacts')
                    ->whereColumn('artefacts.org_stock_id', 'org_stocks.id')
                    ->whereNull('artefacts.deleted_at');
            });

        return PartnerShoppingListItem::whereRoutedToProduction($query)
            ->selectRaw("case
                when partner_shopping_list_items.job_order_id is null then
                    case when partner_shopping_list_items.preparing_at is null then 'backlog' else 'preparing' end
                when job_orders.state in ('in_process', 'submitted') then 'assigned'
                when job_orders.state <> 'confirmed' then 'received'
                when (select count(*) > 0 and bool_and(job_order_item_tasks.state = 'done') $taskStatesSql) then 'done'
                when exists(select 1 $taskStatesSql and job_order_item_tasks.state = 'in_progress') then 'producing'
                else 'assigned'
            end as lane");
    }
}
