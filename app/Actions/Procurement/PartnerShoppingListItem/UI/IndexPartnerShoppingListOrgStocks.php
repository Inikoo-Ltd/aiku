<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PartnerShoppingListItem\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\OrgPartner;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexPartnerShoppingListOrgStocks extends OrgAction
{
    use WithProcurementAuthorisation;

    private OrgPartner $orgPartner;

    public function handle(OrgPartner $orgPartner): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('org_stocks.code', $value)
                    ->orWhereStartWith('org_stocks.name', $value);
            });
        });

        $buyerId = $orgPartner->organisation_id;

        $paginator = QueryBuilder::for(OrgStock::class)
            ->leftJoin('org_stocks as buyer_org_stocks', function ($join) use ($buyerId) {
                $join->on('buyer_org_stocks.stock_id', 'org_stocks.stock_id')
                    ->where('buyer_org_stocks.organisation_id', $buyerId);
            })
            ->leftJoin('partner_shopping_list_items', function ($join) use ($orgPartner) {
                $join->on('partner_shopping_list_items.stock_id', 'org_stocks.stock_id')
                    ->where('partner_shopping_list_items.org_partner_id', $orgPartner->id)
                    ->where('partner_shopping_list_items.state', ShoppingListItemStateEnum::OPEN->value)
                    ->whereNull('partner_shopping_list_items.deleted_at');
            })
            ->where('org_stocks.organisation_id', $orgPartner->partner_id)
            ->where('org_stocks.state', OrgStockStateEnum::ACTIVE)
            ->select([
                'org_stocks.id',
                'org_stocks.code',
                'org_stocks.name',
                'org_stocks.packed_in',
                'org_stocks.quantity_available as available_quantity',
                'buyer_org_stocks.id as buyer_org_stock_id',
                'buyer_org_stocks.quantity_available as buyer_quantity_available',
                'partner_shopping_list_items.id as shopping_list_item_id',
                'partner_shopping_list_items.quantity as quantity_ordered',
            ])
            ->defaultSort('org_stocks.code')
            ->allowedSorts(['code', 'name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator(null, tableName: request()->route()->getName())
            ->withQueryString();

        $this->attachImages($paginator);
        $this->attachBuyerUsage($paginator);
        $this->attachRoutes($paginator, $orgPartner);

        return $paginator;
    }

    private function attachImages(LengthAwarePaginator $paginator): void
    {
        $orgStockIds = $paginator->getCollection()->pluck('id')->unique()->values();

        if ($orgStockIds->isEmpty()) {
            return;
        }

        $orgStocks = OrgStock::with('tradeUnits.image')->whereIn('id', $orgStockIds)->get()->keyBy('id');

        $paginator->getCollection()->transform(function ($row) use ($orgStocks) {
            $tradeUnit = $orgStocks->get($row->id)?->tradeUnits->first(fn ($tradeUnit) => $tradeUnit->image_id !== null);
            $row->image_sources = $tradeUnit?->imageSources(64, 64);

            return $row;
        });
    }

    private function attachBuyerUsage(LengthAwarePaginator $paginator): void
    {
        $buyerOrgStockIds = $paginator->getCollection()->pluck('buyer_org_stock_id')->filter()->unique()->values();

        if ($buyerOrgStockIds->isEmpty()) {
            return;
        }

        $usage = DB::table('delivery_note_items')
            ->whereIn('org_stock_id', $buyerOrgStockIds)
            ->where('quantity_dispatched', '>', 0)
            ->where('created_at', '>=', now()->subMonths(12))
            ->selectRaw("org_stock_id, to_char(date_trunc('quarter', created_at), 'YYYY\"Q\"Q') as period, sum(quantity_dispatched) as sales")
            ->groupByRaw("org_stock_id, date_trunc('quarter', created_at)")
            ->orderByRaw("date_trunc('quarter', created_at)")
            ->get()
            ->groupBy('org_stock_id')
            ->map(fn ($records) => $records->take(-4)->values()->map(fn ($record) => [
                'period' => $record->period,
                'sales'  => round((float) $record->sales, 1),
            ]));

        $paginator->getCollection()->transform(function ($row) use ($usage) {
            $row->buyer_quarterly_usage = $row->buyer_org_stock_id
                ? ($usage->get($row->buyer_org_stock_id) ?? collect())->values()
                : collect();

            return $row;
        });
    }

    private function attachRoutes(LengthAwarePaginator $paginator, OrgPartner $orgPartner): void
    {
        $organisationSlug = $orgPartner->organisation->slug;

        $paginator->getCollection()->transform(function ($row) use ($orgPartner, $organisationSlug) {
            if ($row->shopping_list_item_id) {
                $row->saveRoute = [
                    'method'     => 'patch',
                    'name'       => 'grp.org.procurement.org_partners.show.shopping_list.update',
                    'parameters' => [
                        'organisation'            => $organisationSlug,
                        'orgPartner'              => $orgPartner->id,
                        'partnerShoppingListItem' => $row->shopping_list_item_id,
                    ],
                ];
                $row->deleteRoute = [
                    'method'     => 'delete',
                    'name'       => 'grp.org.procurement.org_partners.show.shopping_list.destroy',
                    'parameters' => [
                        'organisation'            => $organisationSlug,
                        'orgPartner'              => $orgPartner->id,
                        'partnerShoppingListItem' => $row->shopping_list_item_id,
                    ],
                ];
            } else {
                $row->saveRoute = [
                    'method'     => 'post',
                    'name'       => 'grp.org.procurement.org_partners.show.shopping_list.store',
                    'parameters' => [
                        'organisation' => $organisationSlug,
                        'orgPartner'   => $orgPartner->id,
                        'orgStock'     => $row->id,
                    ],
                ];
                $row->deleteRoute = null;
            }

            return $row;
        });
    }

    public function asController(OrgPartner $orgPartner, ActionRequest $request): LengthAwarePaginator
    {
        $this->orgPartner = $orgPartner;
        $this->initialisation($orgPartner->organisation, $request);

        return $this->handle($orgPartner);
    }
}
