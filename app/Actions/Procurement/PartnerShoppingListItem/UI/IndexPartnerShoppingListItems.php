<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PartnerShoppingListItem\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\OrgPartner\GetPartnerBuyingPriceFactor;
use App\Actions\Procurement\OrgPartner\UI\ShowOrgPartner;
use App\Actions\Procurement\OrgPartner\WithPartnerShoppingSubNavigation;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\InertiaTable\InertiaTable;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexPartnerShoppingListItems extends OrgAction
{
    use WithProcurementAuthorisation;
    use WithPartnerShoppingSubNavigation;

    private OrgPartner $orgPartner;

    /**
     * Price of one SKO in the selling partner's catalogue, correlated to the item's row.
     */
    public function handle(OrgPartner $orgPartner): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('org_stocks.code', $value)
                    ->orWhereStartWith('org_stocks.name', $value);
            });
        });

        $paginator = QueryBuilder::for(PartnerShoppingListItem::class)
            ->leftJoin('org_stocks', 'org_stocks.id', 'partner_shopping_list_items.org_stock_id')
            ->leftJoin('users', 'users.id', 'partner_shopping_list_items.added_by_user_id')
            ->leftJoin('org_stock_stats', 'org_stock_stats.org_stock_id', 'partner_shopping_list_items.org_stock_id')
            ->leftJoin('org_stocks as partner_org_stocks', function ($join) {
                $join->on('partner_org_stocks.stock_id', 'partner_shopping_list_items.stock_id')
                    ->on('partner_org_stocks.organisation_id', 'partner_shopping_list_items.partner_organisation_id');
            })
            ->leftJoin('org_partners as seller_partner', function ($join) {
                $join->on('seller_partner.organisation_id', 'partner_shopping_list_items.partner_organisation_id')
                    ->on('seller_partner.partner_id', 'partner_shopping_list_items.organisation_id');
            })
            ->leftJoin('job_orders', 'job_orders.id', 'partner_shopping_list_items.job_order_id')
            ->leftJoin('transactions', 'transactions.id', 'partner_shopping_list_items.transaction_id')
            ->leftJoin('orders', 'orders.id', 'transactions.order_id')
            ->where('partner_shopping_list_items.org_partner_id', $orgPartner->id)
            ->select([
                'partner_shopping_list_items.id',
                'partner_shopping_list_items.quantity',
                'partner_shopping_list_items.priority',
                'partner_shopping_list_items.state',
                'partner_shopping_list_items.needed_by',
                'partner_shopping_list_items.notes',
                'partner_shopping_list_items.created_at',
                'partner_shopping_list_items.org_stock_id',
                'org_stocks.code as org_stock_code',
                'org_stocks.name as org_stock_name',
                'org_stocks.quantity_available as buyer_available',
                'users.contact_name as added_by_name',
                'org_stock_stats.days_of_cover',
                'partner_org_stocks.quantity_available as their_available',
                'job_orders.reference as job_order_reference',
                'job_orders.state as job_order_state',
                'orders.reference as order_reference',
                'orders.state as order_state',
                DB::raw("(select coalesce(sum(location_org_stocks.quantity), 0) from location_org_stocks
                    where location_org_stocks.location_id = seller_partner.goods_out_location_id
                        and location_org_stocks.org_stock_id = partner_org_stocks.id) as quantity_staged"),
                DB::raw("(select delivery_notes.reference from delivery_notes
                    join delivery_note_order on delivery_note_order.delivery_note_id = delivery_notes.id
                    where delivery_note_order.order_id = orders.id and delivery_notes.deleted_at is null
                    order by delivery_notes.id desc limit 1) as delivery_note_reference"),
                DB::raw("(select delivery_notes.state from delivery_notes
                    join delivery_note_order on delivery_note_order.delivery_note_id = delivery_notes.id
                    where delivery_note_order.order_id = orders.id and delivery_notes.deleted_at is null
                    order by delivery_notes.id desc limit 1) as delivery_note_state"),
            ])
            ->selectRaw(PartnerShoppingListItem::pricePerSkoSql().' as price_per_sko')
            ->defaultSort('-created_at')
            ->allowedFilters([$globalSearch])
            ->allowedSorts(['org_stock_code', 'priority', 'needed_by', 'state', 'created_at'])
            ->withPaginator(null, tableName: request()->route()->getName())
            ->withQueryString();

        $this->attachImages($paginator);

        return $paginator;
    }

    /**
     * One extra eager-loaded query for the whole page, not per row.
     */
    private function attachImages(LengthAwarePaginator $paginator): void
    {
        $orgStockIds = $paginator->getCollection()->pluck('org_stock_id')->filter()->unique()->values();

        if ($orgStockIds->isEmpty()) {
            return;
        }

        $orgStocks = OrgStock::with('tradeUnits.image')->whereIn('id', $orgStockIds)->get()->keyBy('id');
        $exchange  = $this->orgPartner->exchangeToOrgCurrency() * GetPartnerBuyingPriceFactor::run($this->orgPartner);

        $paginator->getCollection()->transform(function ($row) use ($orgStocks, $exchange) {
            $tradeUnit = $orgStocks->get($row->org_stock_id)?->tradeUnits->first(fn ($tradeUnit) => $tradeUnit->image_id !== null);
            $row->image_sources = $tradeUnit?->imageSources(48, 48);
            $row->price_per_sko = $row->price_per_sko === null ? null : round((float) $row->price_per_sko * $exchange, 4);
            $row->progress      = $this->progressOf($row);

            return $row;
        });
    }

    /**
     * Where the line actually is, told from what exists rather than from a status column:
     * a job order means it is being made, a goods out location holding it means it is
     * staged, a delivery note means it has left.
     *
     * @return array{label: string, tone: string, reference: string|null}
     */
    private function progressOf(object $row): array
    {
        if ($row->delivery_note_reference) {
            return $row->delivery_note_state === 'dispatched'
                ? ['label' => __('On its way'), 'tone' => 'emerald', 'reference' => $row->delivery_note_reference]
                : ['label' => __('Being picked'), 'tone' => 'indigo', 'reference' => $row->delivery_note_reference];
        }

        if ((float) $row->quantity_staged > 0) {
            return ['label' => __('Staged for you'), 'tone' => 'emerald', 'reference' => $row->order_reference];
        }

        if ($row->order_reference) {
            return ['label' => __('Pre-picked'), 'tone' => 'indigo', 'reference' => $row->order_reference];
        }

        if ($row->job_order_reference) {
            return ['label' => __('Being made'), 'tone' => 'amber', 'reference' => $row->job_order_reference];
        }

        return ['label' => __('Requested'), 'tone' => 'gray', 'reference' => null];
    }

    public function tableStructure(OrgPartner $orgPartner): Closure
    {
        return function (InertiaTable $table) use ($orgPartner) {
            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('Shopping list item'), __('Shopping list items')])
                ->withEmptyState([
                    'title' => __('No items on the shopping list'),
                ])
                ->withFooterNote(
                    __('Open items value').': '
                    .$orgPartner->organisation->currency->code.' '
                    .number_format((float) $orgPartner->stats->open_shopping_list_items_value * $orgPartner->exchangeToOrgCurrency() * GetPartnerBuyingPriceFactor::run($orgPartner), 2)
                )
                ->column(key: 'org_stock_code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'info', label: __('Info'), canBeHidden: false)
                ->column(key: 'quantity', label: __('Quantity (SKO)'), canBeHidden: false, align: 'right')
                ->column(key: 'amount', label: __('Amount'), canBeHidden: false, align: 'right')
                ->column(key: 'priority', label: __('Priority'), canBeHidden: false, sortable: true)
                ->column(key: 'progress', label: __('Progress'), canBeHidden: false)
                ->column(key: 'state', label: __('State'), canBeHidden: false, sortable: true)
                ->column(key: 'created_at', label: __('Added'), canBeHidden: false, sortable: true)
                ->column(key: 'actions', label: '', canBeHidden: false, align: 'right')
                ->defaultSort('-created_at');
        };
    }

    public function asController(Organisation $organisation, OrgPartner $orgPartner, ActionRequest $request): LengthAwarePaginator
    {
        $this->orgPartner = $orgPartner;
        $this->initialisation($organisation, $request);

        return $this->handle($orgPartner);
    }

    public function htmlResponse(LengthAwarePaginator $items, ActionRequest $request): Response
    {
        return Inertia::render(
            'Procurement/PartnerShoppingList',
            [
                'breadcrumbs' => $this->getBreadcrumbs($this->orgPartner, $request->route()->originalParameters()),
                'title'       => __('Shopping list'),
                'pageHead'    => [
                    'icon'          => [
                        'icon'  => ['fal', 'fa-shopping-basket'],
                        'title' => __('Shopping list'),
                    ],
                    'model'         => $this->orgPartner->partner->name,
                    'title'         => __('Shopping list'),
                    'subNavigation' => $this->getPartnerShoppingNavigation($this->orgPartner),
                ],
                'orgPartner'         => [
                    'id'       => $this->orgPartner->id,
                    'slug'     => $this->orgPartner->partner->slug,
                    'currency' => $this->orgPartner->organisation->currency->code,
                ],
                'orgStockFetchRoute' => [
                    'name'       => 'grp.json.org_partner.shopping_list_org_stocks',
                    'parameters' => [
                        'orgPartner' => $this->orgPartner->id,
                    ],
                ],
                'data' => $items,
            ]
        )->table($this->tableStructure($this->orgPartner));
    }

    public function getBreadcrumbs(OrgPartner $orgPartner, array $routeParameters): array
    {
        return array_merge(
            ShowOrgPartner::make()->getBreadcrumbs($orgPartner, $routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.procurement.org_partners.show.shopping_list.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Shopping list'),
                        'icon'  => 'fal fa-shopping-basket',
                    ],
                ],
            ]
        );
    }
}
