<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PartnerShoppingListItem\UI;

use App\Actions\Inventory\OrgStock\GetOrgStocksQuarterlyUsage;
use App\Actions\Inventory\OrgStock\GetOrgStocksStockDeliveries;
use App\Actions\Procurement\OrgPartner\GetPartnerLeadTime;
use App\Actions\Procurement\PartnerShoppingListItem\RoundPartnerQuantityToBatches;
use App\Actions\Procurement\PurchaseOrder\UI\GetOrgStockBuyingSignals;
use App\Actions\Procurement\PurchaseOrder\UI\IndexPurchaseOrderOrgSupplierProducts;
use App\Actions\Procurement\OrgPartner\GetPartnerSellingShopIds;
use App\Actions\OrgAction;
use App\Actions\Procurement\OrgPartner\GetPartnerBuyingPriceFactor;
use App\Actions\Procurement\OrgPartner\UI\ShowOrgPartner;
use App\Actions\Procurement\OrgPartner\WithPartnerShoppingSubNavigation;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Enums\Catalogue\HealthRankEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Support\Collection;
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

    private bool $isSentView = false;

    /**
     * The ongoing PO is the drafts staff build and submit; the sent view follows, read only, what the
     * partner does with the submitted lines.
     *
     * @return array<int, string>
     */
    private function statesInView(): array
    {
        return $this->isSentView
            ? [ShoppingListItemStateEnum::OPEN->value, ShoppingListItemStateEnum::ORDERED->value]
            : [ShoppingListItemStateEnum::DRAFT->value];
    }

    private const string NO_CATEGORY = 'none';

    private const string CATEGORY_SQL = '(select artefacts.artefact_department_id from artefacts
        where artefacts.org_stock_id = partner_org_stocks.id and artefacts.deleted_at is null
        order by artefacts.id limit 1)';

    /**
     * @return array<string, array{label: string, elements: array<string, array{0: string, 1: int}>, engine: Closure}>
     */
    private function getElementGroups(OrgPartner $orgPartner): array
    {
        $items = fn () => DB::table('partner_shopping_list_items')
            ->where('partner_shopping_list_items.org_partner_id', $orgPartner->id)
            ->whereIn('partner_shopping_list_items.state', $this->statesInView())
            ->whereNull('partner_shopping_list_items.deleted_at');

        $stateCounts = $items()->selectRaw('state, count(*) as total')->groupBy('state')->pluck('total', 'state');
        $rankCounts  = $items()
            ->join('org_stocks', 'org_stocks.id', 'partner_shopping_list_items.org_stock_id')
            ->selectRaw("coalesce(org_stocks.health_rank, '-') as rank, count(*) as total")
            ->groupByRaw("coalesce(org_stocks.health_rank, '-')")
            ->pluck('total', 'rank');
        $categoryCounts = $items()
            ->leftJoin('org_stocks as partner_org_stocks', function ($join) {
                $join->on('partner_org_stocks.stock_id', 'partner_shopping_list_items.stock_id')
                    ->on('partner_org_stocks.organisation_id', 'partner_shopping_list_items.partner_organisation_id');
            })
            ->selectRaw(self::CATEGORY_SQL.' as category_id, count(*) as total')
            ->groupByRaw(self::CATEGORY_SQL)
            ->pluck('total', 'category_id');
        $categoryNames = DB::table('artefact_departments')->whereIn('id', $categoryCounts->keys()->filter())->orderBy('name')->pluck('name', 'id');

        $states = [
            ShoppingListItemStateEnum::OPEN->value    => __('Waiting for the partner'),
            ShoppingListItemStateEnum::ORDERED->value => __('Ordered'),
        ];

        return [
            ...($this->isSentView ? ['state' => [
                'label'    => __('State'),
                'elements' => collect($states)->map(fn ($label, $state) => [$label, (int) ($stateCounts[$state] ?? 0)])->all(),
                'engine'   => fn ($query, $elements) => $query->whereIn('partner_shopping_list_items.state', $elements),
            ]] : []),
            'rank'     => [
                'label'    => __('Rank'),
                'elements' => collect(HealthRankEnum::cases())->mapWithKeys(fn (HealthRankEnum $rank) => [
                    $rank->value => [$rank->value, (int) ($rankCounts[$rank->value] ?? 0)],
                ])->all(),
                'engine'   => fn ($query, $elements) => $query->whereIn('org_stocks.health_rank', $elements),
            ],
            'category' => [
                'label'    => __('Category'),
                'elements' => $categoryNames->mapWithKeys(fn ($name, $id) => [(string) $id => [$name, (int) $categoryCounts[$id]]])
                    ->put(self::NO_CATEGORY, [__('Other'), (int) ($categoryCounts[''] ?? 0)])
                    ->all(),
                'engine'   => function ($query, $elements) {
                    $query->where(function ($query) use ($elements) {
                        $query->whereIn(DB::raw(self::CATEGORY_SQL), array_map('intval', array_diff($elements, [self::NO_CATEGORY])));
                        if (in_array(self::NO_CATEGORY, $elements, true)) {
                            $query->orWhereRaw(self::CATEGORY_SQL.' is null');
                        }
                    });
                },
            ],
        ];
    }

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

        $queryBuilder = QueryBuilder::for(PartnerShoppingListItem::class)
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
            ->whereIn('partner_shopping_list_items.state', $this->statesInView());

        foreach ($this->getElementGroups($orgPartner) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
            );
        }

        $paginator = $queryBuilder
            ->select([
                'partner_shopping_list_items.id',
                'partner_shopping_list_items.quantity',
                'partner_shopping_list_items.priority',
                'partner_shopping_list_items.state',
                'partner_shopping_list_items.needed_by',
                'partner_shopping_list_items.notes',
                'partner_shopping_list_items.created_at',
                'partner_shopping_list_items.pre_picked_at',
                'partner_shopping_list_items.org_stock_id',
                'org_stocks.code as org_stock_code',
                'org_stocks.name as org_stock_name',
                'org_stocks.quantity_available as buyer_available',
                'users.contact_name as added_by_name',
                'org_stock_stats.days_of_cover',
                'partner_org_stocks.quantity_available as their_available',
                DB::raw(RoundPartnerQuantityToBatches::quantumSql('partner_org_stocks').' as order_quantum'),
                'job_orders.reference as job_order_reference',
                'job_orders.state as job_order_state',
                'orders.reference as order_reference',
                'orders.state as order_state',
                DB::raw("(select coalesce(sum(location_org_stocks.quantity), 0) from location_org_stocks
                    where location_org_stocks.location_id = ".OrgPartner::bayIdSql('seller_partner', '(select stocks.is_cosmetic from stocks where stocks.id = partner_shopping_list_items.stock_id)')."
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
            ->selectRaw(PartnerShoppingListItem::pricePerSkoSql(GetPartnerSellingShopIds::run($orgPartner->partner)).' as price_per_sko')
            ->defaultSort('-created_at')
            ->allowedFilters([$globalSearch])
            ->allowedSorts(['org_stock_code', 'priority', 'needed_by', 'state', 'created_at'])
            ->withPaginator(null, tableName: request()->route()->getName())
            ->withQueryString();

        $this->attachDetails($orgPartner, $paginator);

        return $paginator;
    }

    /**
     * The same stock picture a purchase order line shows (stock, cover, deliveries, other open orders),
     * so the list can be fine tuned the same way. Quantities on the list are SKOs, the stock cover
     * works in units, hence the packed_in conversions; the hub makes in batches, which play the carton.
     */
    private function attachDetails(OrgPartner $orgPartner, LengthAwarePaginator $paginator): void
    {
        $orgStockIds = $paginator->getCollection()->pluck('org_stock_id')->filter()->unique()->values();

        if ($orgStockIds->isEmpty()) {
            return;
        }

        $orgStocks       = OrgStock::with(['tradeUnits.image', 'stats', 'stock.stockFamily'])->whereIn('id', $orgStockIds)->get()->keyBy('id');
        $exchange        = $orgPartner->exchangeToOrgCurrency() * GetPartnerBuyingPriceFactor::run($orgPartner);
        $quarterlyUsage  = GetOrgStocksQuarterlyUsage::run($orgStockIds);
        $stockDeliveries = GetOrgStocksStockDeliveries::run($orgStockIds);
        $leadTimeDays    = GetPartnerLeadTime::run($orgPartner)['days'];

        $paginator->getCollection()->transform(function ($row) use ($orgStocks, $exchange, $quarterlyUsage, $stockDeliveries, $leadTimeDays) {
            $orgStock  = $orgStocks->get($row->org_stock_id);
            $tradeUnit = $orgStock?->tradeUnits->first(fn ($tradeUnit) => $tradeUnit->image_id !== null);
            $packedIn  = (float) ($orgStock?->packed_in ?: 1);

            $row->image_sources            = $tradeUnit?->imageSources(160, 160);
            $row->price_per_sko            = $row->price_per_sko === null ? null : round((float) $row->price_per_sko * $exchange, 4);
            $row->progress                 = $this->progressOf($row);
            $row->stock_in_locations       = $orgStock?->quantity_in_locations === null ? null : trimDecimalZeros($orgStock->quantity_in_locations);
            $row->stock_cover              = $orgStock ? GetOrgStockBuyingSignals::run($orgStock, null, $leadTimeDays) : null;
            $row->quarterly_usage          = $quarterlyUsage->get($row->org_stock_id) ?? collect();
            $row->stock_deliveries         = $stockDeliveries->get($row->org_stock_id);
            $row->units_per_pack           = $packedIn;
            $row->quantity_ordered         = (float) $row->quantity * $packedIn;
            $row->partner_units_per_carton = (float) ($row->order_quantum ?: 1) * $packedIn;
            $row->whole_cartons_only       = true;
            $row->partner_stock            = null;

            return $row;
        });

        IndexPurchaseOrderOrgSupplierProducts::make()->attachOtherOpenPurchaseOrders($paginator, $orgPartner->organisation_id);
        $this->attachSentToPartner($orgPartner, $paginator, $orgStocks);
    }

    /**
     * Lines already sent to this partner count as incoming stock for the same SKO, so the suggestion
     * does not order it twice.
     *
     * @param Collection<int, OrgStock> $orgStocks
     */
    private function attachSentToPartner(OrgPartner $orgPartner, LengthAwarePaginator $paginator, Collection $orgStocks): void
    {
        $rows = $paginator->getCollection();

        $sentSkos = DB::table('partner_shopping_list_items')
            ->where('org_partner_id', $orgPartner->id)
            ->whereIn('org_stock_id', $rows->pluck('org_stock_id')->filter()->unique()->values())
            ->whereIn('state', [ShoppingListItemStateEnum::OPEN->value, ShoppingListItemStateEnum::ORDERED->value])
            ->whereNull('deleted_at')
            ->selectRaw('org_stock_id, id, quantity')
            ->get()
            ->groupBy('org_stock_id');

        $partnerName = $orgPartner->partner->name;

        $rows->transform(function ($row) use ($sentSkos, $orgStocks, $partnerName) {
            $skos = (float) $sentSkos->get($row->org_stock_id, collect())->where('id', '!=', $row->id)->sum('quantity');

            if ($skos > 0) {
                $packedIn = (float) ($orgStocks->get($row->org_stock_id)?->packed_in ?: 1);

                $row->other_open_purchase_orders = collect($row->other_open_purchase_orders ?? [])->push([
                    'slug'             => null,
                    'reference'        => __('Sent to :partner', ['partner' => $partnerName]),
                    'state'            => 'sent',
                    'quantity_ordered' => $skos * $packedIn,
                ])->all();
            }

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
        if ($row->state === ShoppingListItemStateEnum::DRAFT) {
            return ['label' => __('Not sent'), 'tone' => 'gray', 'reference' => null];
        }

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
                ->withLabelRecord([__('line'), __('lines')]);

            foreach ($this->getElementGroups($orgPartner) as $key => $elementGroup) {
                $table->elementGroup(key: $key, label: $elementGroup['label'], elements: $elementGroup['elements']);
            }

            $table
                ->withEmptyState([
                    'title' => $this->isSentView ? __('Nothing sent to the partner is open') : __('The ongoing PO is empty'),
                ])
                ->column(key: 'org_stock_code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'info', label: __('SKO description'), canBeHidden: false)
                ->column(key: 'quantity', label: __('SKOs'), canBeHidden: false, align: 'right')
                ->column(key: 'amount', label: __('Amount'), canBeHidden: false, align: 'right')
                ->column(key: 'priority', label: __('Priority'), canBeHidden: false, sortable: true);

            if ($this->isSentView) {
                $table
                    ->column(key: 'progress', label: __('Progress'), canBeHidden: false)
                    ->column(key: 'state', label: __('State'), canBeHidden: false, sortable: true);
            }

            $table->column(key: 'created_at', label: __('Added'), canBeHidden: false, sortable: true);

            if (!$this->isSentView) {
                $table->column(key: 'actions', label: '', canBeHidden: false, align: 'right');
            }

            $table->defaultSort('-created_at');
        };
    }

    public function asController(Organisation $organisation, OrgPartner $orgPartner, ActionRequest $request): LengthAwarePaginator
    {
        abort_unless($orgPartner->organisation_id === $organisation->id, 404);
        $this->orgPartner = $orgPartner;
        $this->initialisation($organisation, $request);

        return $this->handle($orgPartner);
    }

    public function inSent(Organisation $organisation, OrgPartner $orgPartner, ActionRequest $request): LengthAwarePaginator
    {
        $this->isSentView = true;

        return $this->asController($organisation, $orgPartner, $request);
    }

    public function htmlResponse(LengthAwarePaginator $items, ActionRequest $request): Response
    {
        $pageTitle = $this->isSentView ? __('Sent to :partner', ['partner' => $this->orgPartner->partner->name]) : __('Ongoing PO');
        $pageIcon  = $this->isSentView ? 'fa-paper-plane' : 'fa-shopping-basket';

        return Inertia::render(
            'Procurement/PartnerShoppingList',
            [
                'breadcrumbs' => $this->getBreadcrumbs($this->orgPartner, $request->route()->originalParameters()),
                'title'       => '(' . $this->orgPartner->partner->code . ') ' . $pageTitle,
                'pageHead'    => [
                    'icon'          => [
                        'icon'  => ['fal', $pageIcon],
                        'title' => $pageTitle,
                    ],
                    'model'         => $this->orgPartner->partner->name,
                    'title'         => $this->isSentView ? __('Sent') : __('Ongoing PO'),
                    'actions'       => [
                        [
                            'type'  => 'button',
                            'style' => 'exit',
                            'label' => __('Partners'),
                            'route' => [
                                'name'       => 'grp.org.procurement.org_partners.index',
                                'parameters' => [$this->orgPartner->organisation->slug],
                            ],
                        ],
                    ],
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
                'upload_excel'       => [
                    'title'               => [
                        'label'       => __('Upload SKOs'),
                        'information' => __('Columns: code (our SKO code) and quantity (in SKOs). SKOs already on the list get the new quantity, raised to whole production batches.'),
                    ],
                    'progressDescription' => __('Adding SKOs to the shopping list'),
                    'preview_template'    => [
                        'header' => ['code', 'quantity'],
                        'rows'   => [
                            ['code' => 'SKO-001', 'quantity' => '24'],
                        ],
                    ],
                    'upload_spreadsheet'  => [
                        'event'           => 'action-progress',
                        'channel'         => 'grp.personal.'.$request->user()->id,
                        'required_fields' => ['code', 'quantity'],
                        'route'           => [
                            'upload'  => [
                                'name'       => 'grp.org.procurement.org_partners.show.shopping_list.upload',
                                'parameters' => [$this->orgPartner->organisation->slug, $this->orgPartner->id],
                            ],
                            'history' => [
                                'name'       => 'grp.json.org_partner.shopping_list.recent_uploads',
                                'parameters' => ['orgPartner' => $this->orgPartner->id],
                            ],
                        ],
                    ],
                ],
                'isSentView'  => $this->isSentView,
                'linesValue'  => $this->linesValue($this->orgPartner),
                'draftsCount' => PartnerShoppingListItem::where('org_partner_id', $this->orgPartner->id)
                    ->where('state', ShoppingListItemStateEnum::DRAFT)
                    ->count(),
                'data' => $items,
            ]
        )->table($this->tableStructure($this->orgPartner));
    }

    private function linesValue(OrgPartner $orgPartner): float
    {
        $value = DB::table('partner_shopping_list_items')
            ->where('org_partner_id', $orgPartner->id)
            ->whereIn('state', $this->statesInView())
            ->whereNull('deleted_at')
            ->selectRaw('coalesce(sum(quantity * coalesce('.PartnerShoppingListItem::pricePerSkoSql(GetPartnerSellingShopIds::run($orgPartner->partner)).', 0)), 0) as value')
            ->value('value');

        return round((float) $value * $orgPartner->exchangeToOrgCurrency() * GetPartnerBuyingPriceFactor::run($orgPartner), 2);
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
                            'name'       => $this->isSentView ? 'grp.org.procurement.org_partners.show.shopping_list.sent' : 'grp.org.procurement.org_partners.show.shopping_list.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => $this->isSentView ? __('Sent') : __('Ongoing PO'),
                        'icon'  => $this->isSentView ? 'fal fa-paper-plane' : 'fal fa-shopping-basket',
                    ],
                ],
            ]
        );
    }
}
