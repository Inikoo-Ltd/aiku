<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PartnerShoppingListItem\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\OrgPartner\UI\ShowOrgPartner;
use App\Actions\Procurement\OrgPartner\WithPartnerShoppingSubNavigation;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
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
    private function pricePerSkoSubQuery(): string
    {
        return "(select pr.price / nullif(phos.quantity, 0)
            from product_has_org_stocks phos
            join products pr on pr.id = phos.product_id and pr.state = '".ProductStateEnum::ACTIVE->value."'
            join org_stocks sos on sos.id = phos.org_stock_id
            where sos.stock_id = partner_shopping_list_items.stock_id
                and sos.organisation_id = partner_shopping_list_items.partner_organisation_id
            limit 1)";
    }

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
            ])
            ->selectRaw($this->pricePerSkoSubQuery().' as price_per_sko')
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

        $paginator->getCollection()->transform(function ($row) use ($orgStocks) {
            $tradeUnit = $orgStocks->get($row->org_stock_id)?->tradeUnits->first(fn ($tradeUnit) => $tradeUnit->image_id !== null);
            $row->image_sources = $tradeUnit?->imageSources(48, 48);

            return $row;
        });
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
                    .$orgPartner->partner->currency->code.' '
                    .number_format($this->openItemsValue($orgPartner), 2)
                )
                ->column(key: 'image', label: __('Image'), canBeHidden: false)
                ->column(key: 'org_stock_code', label: __('Stock'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'org_stock_name', label: __('Name'), canBeHidden: false)
                ->column(key: 'quantity', label: __('Quantity (SKO)'), canBeHidden: false, align: 'right')
                ->column(key: 'amount', label: __('Amount'), canBeHidden: false, align: 'right')
                ->column(key: 'priority', label: __('Priority'), canBeHidden: false, sortable: true)
                ->column(key: 'needed_by', label: __('Needed by'), canBeHidden: false, sortable: true)
                ->column(key: 'state', label: __('State'), canBeHidden: false, sortable: true)
                ->column(key: 'created_at', label: __('Added'), canBeHidden: false, sortable: true)
                ->column(key: 'actions', label: '', canBeHidden: false, align: 'right')
                ->defaultSort('-created_at');
        };
    }

    private function openItemsValue(OrgPartner $orgPartner): float
    {
        return (float) DB::table('partner_shopping_list_items')
            ->where('org_partner_id', $orgPartner->id)
            ->where('state', ShoppingListItemStateEnum::OPEN->value)
            ->whereNull('deleted_at')
            ->selectRaw('coalesce(sum(quantity * coalesce('.$this->pricePerSkoSubQuery().', 0)), 0) as total')
            ->value('total');
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
                    'currency' => $this->orgPartner->partner->currency->code,
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
