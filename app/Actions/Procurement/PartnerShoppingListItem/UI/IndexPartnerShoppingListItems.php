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
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemPriorityEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Carbon\Carbon;
use Closure;
use Illuminate\Database\Eloquent\Builder;
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

    /** @var Collection<int, Collection<int, array{name: string, made: float, required: float, who: string|null, last_at: string|null}>> */
    private Collection $productionSteps;

    private ?array $filterGroups = null;

    /**
     * The ongoing PO is the drafts staff build and submit; the sent view follows what the partner does
     * with the submitted lines, which can still be changed or removed until the partner starts them.
     *
     * @return array<int, string>
     */
    private function statesInView(): array
    {
        return $this->isSentView
            ? [ShoppingListItemStateEnum::OPEN->value, ShoppingListItemStateEnum::ORDERED->value, ShoppingListItemStateEnum::DISMISSED->value]
            : [ShoppingListItemStateEnum::DRAFT->value];
    }

    private const string NO_CATEGORY = 'none';

    private const string CATEGORY_SQL = '(select artefacts.artefact_department_id from artefacts
        where artefacts.org_stock_id = partner_org_stocks.id and artefacts.deleted_at is null
        order by artefacts.id limit 1)';

    /**
     * @return array<string, array{label: string, options: array<int, array{value: string, label: string, count: int}>, engine: Closure}>
     */
    private function filterGroups(OrgPartner $orgPartner): array
    {
        if ($this->filterGroups !== null) {
            return $this->filterGroups;
        }

        $items = fn () => DB::table('partner_shopping_list_items')
            ->where('partner_shopping_list_items.org_partner_id', $orgPartner->id)
            ->whereIn('partner_shopping_list_items.state', $this->statesInView())
            ->whereNull('partner_shopping_list_items.deleted_at')
            ->when($this->isSentView, fn ($query) => PartnerShoppingListItem::whereNotSplitPiece($query, $this->statesInView()));

        $stateOfLineOrPiece = fn ($query, array $values) => $query->where(function ($query) use ($values) {
            $placeholders = implode(', ', array_fill(0, count($values), '?'));
            $query->whereIn('partner_shopping_list_items.state', $values)
                ->orWhereRaw("exists (with recursive pieces as (
                        select id, state from partner_shopping_list_items as piece
                        where piece.parent_id = partner_shopping_list_items.id and piece.deleted_at is null
                        union all
                        select piece.id, piece.state from partner_shopping_list_items as piece
                        join pieces on piece.parent_id = pieces.id
                        where piece.deleted_at is null
                    ) select 1 from pieces where pieces.state in ($placeholders))", $values);
        });
        $stateCounts = collect([ShoppingListItemStateEnum::OPEN->value, ShoppingListItemStateEnum::ORDERED->value, ShoppingListItemStateEnum::DISMISSED->value])
            ->mapWithKeys(fn ($state) => [$state => $this->isSentView ? $stateOfLineOrPiece($items(), [$state])->count() : 0]);
        $rankCounts  = $items()
            ->join('org_stocks', 'org_stocks.id', 'partner_shopping_list_items.org_stock_id')
            ->selectRaw('org_stocks.health_rank as rank, count(*) as total')
            ->groupBy('org_stocks.health_rank')
            ->pluck('total', 'rank');
        $originCounts   = $items()->selectRaw('suggested_by_hub, count(*) as total')->groupBy('suggested_by_hub')->pluck('total', 'suggested_by_hub');
        $categoryCounts = $items()
            ->leftJoin('org_stocks as partner_org_stocks', function ($join) {
                $join->on('partner_org_stocks.stock_id', 'partner_shopping_list_items.stock_id')
                    ->on('partner_org_stocks.organisation_id', 'partner_shopping_list_items.partner_organisation_id');
            })
            ->selectRaw(self::CATEGORY_SQL.' as category_id, count(*) as total')
            ->groupByRaw(self::CATEGORY_SQL)
            ->pluck('total', 'category_id');

        $option     = fn (string $value, string $label, $count) => ['value' => $value, 'label' => $label, 'count' => (int) $count];
        $withCounts = fn (Collection $options) => $options->filter(fn ($option) => $option['count'] > 0)->values()->all();

        return $this->filterGroups = [
            ...($this->isSentView ? ['state' => [
                'label'   => __('State'),
                'options' => $withCounts(collect([
                    $option(ShoppingListItemStateEnum::OPEN->value, __('Waiting for the partner'), $stateCounts[ShoppingListItemStateEnum::OPEN->value] ?? 0),
                    $option(ShoppingListItemStateEnum::ORDERED->value, __('Ordered'), $stateCounts[ShoppingListItemStateEnum::ORDERED->value] ?? 0),
                    $option(ShoppingListItemStateEnum::DISMISSED->value, __('Cannot be made'), $stateCounts[ShoppingListItemStateEnum::DISMISSED->value] ?? 0),
                ])),
                'engine'  => $stateOfLineOrPiece,
            ]] : []),
            'category' => [
                'label'   => __('Category'),
                'options' => $withCounts(
                    DB::table('artefact_departments')->whereIn('id', $categoryCounts->keys()->filter())->pluck('name', 'id')
                        ->map(fn ($name, $id) => $option((string) $id, $name, $categoryCounts[$id]))
                        ->sortByDesc('count')
                        ->push($option(self::NO_CATEGORY, __('Other'), $categoryCounts[''] ?? 0))
                ),
                'engine'  => function ($query, array $values) {
                    $query->where(function ($query) use ($values) {
                        $query->whereIn(DB::raw(self::CATEGORY_SQL), array_map('intval', array_diff($values, [self::NO_CATEGORY])));
                        if (in_array(self::NO_CATEGORY, $values, true)) {
                            $query->orWhereRaw(self::CATEGORY_SQL.' is null');
                        }
                    });
                },
            ],
            'origin'   => [
                'label'   => __('Added by'),
                'options' => [
                    $option('us', __('Us'), $originCounts[0] ?? 0),
                    $option('hub', __(':partner suggested', ['partner' => $orgPartner->partner->code]), $originCounts[1] ?? 0),
                ],
                'engine'  => fn ($query, array $values) => $query->whereIn('partner_shopping_list_items.suggested_by_hub', array_map(fn ($value) => $value === 'hub', $values)),
            ],
            'rank'     => [
                'label'   => __('Rank'),
                'options' => $withCounts(collect(HealthRankEnum::cases())->map(fn (HealthRankEnum $rank) => $option($rank->value, $rank->value, $rankCounts[$rank->value] ?? 0))),
                'engine'  => fn ($query, array $values) => $query->whereIn('org_stocks.health_rank', $values),
            ],
        ];
    }

    /**
     * @return array<string, array{label: string, elements: array<string, array{0: string, 1: int, 2: null, 3: array{icon: string, class: string, tooltip: string}}>, engine: Closure}>
     */
    private function elementGroups(OrgPartner $orgPartner): array
    {
        $priorityCounts = DB::table('partner_shopping_list_items')
            ->where('org_partner_id', $orgPartner->id)
            ->whereIn('state', $this->statesInView())
            ->whereNull('deleted_at')
            ->when($this->isSentView, fn ($query) => PartnerShoppingListItem::whereNotSplitPiece($query, $this->statesInView()))
            ->selectRaw('priority, count(*) as total')
            ->groupBy('priority')
            ->pluck('total', 'priority');

        return [
            'priority' => [
                'label'    => __('Priority'),
                'elements' => collect(ShoppingListItemPriorityEnum::cases())->mapWithKeys(
                    fn (ShoppingListItemPriorityEnum $priority) => [
                        $priority->value => [
                            ShoppingListItemPriorityEnum::labels()[$priority->value],
                            (int) ($priorityCounts[$priority->value] ?? 0),
                            null,
                            ShoppingListItemPriorityEnum::icons()[$priority->value],
                        ],
                    ]
                )->all(),
                'engine'   => fn ($query, array $elements) => $query->whereIn('partner_shopping_list_items.priority', $elements),
            ],
        ];
    }

    /**
     * Price of one SKO in the selling partner's catalogue, correlated to the item's row.
     */
    public function handle(OrgPartner $orgPartner): LengthAwarePaginator
    {
        $this->productionSteps = collect();

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('org_stocks.code', $value)
                    ->orWhereStartWith('org_stocks.name', $value);
            });
        });

        $queryBuilder = $this->linesQuery(QueryBuilder::for(PartnerShoppingListItem::class), $orgPartner)
            ->whereIn('partner_shopping_list_items.state', $this->statesInView())
        ;

        if ($this->isSentView) {
            PartnerShoppingListItem::whereNotSplitPiece($queryBuilder->getEloquentBuilder(), $this->statesInView());
        }

        foreach ($this->elementGroups($orgPartner) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
            );
        }

        $paginator = $queryBuilder
            ->defaultSort('-created_at')
            ->allowedFilters([
                $globalSearch,
                ...collect($this->filterGroups($orgPartner))->map(fn ($group, $key) => AllowedFilter::callback($key, fn ($query, $value) => $group['engine']($query, (array) $value)))->values(),
            ])
            ->allowedSorts(['org_stock_code', 'priority', 'needed_by', 'state', 'created_at'])
            ->withPaginator(null, tableName: request()->route()->getName())
            ->withQueryString();

        if ($this->isSentView) {
            $this->foldSplitLines($orgPartner, $paginator);
        }

        $this->attachDetails($orgPartner, $paginator);

        return $paginator;
    }

    /**
     * Picking part from stock and making the rest splits a line in two, which the buyer never asked
     * for: the pieces show back as the one line they ordered, with how far each part has got.
     */
    private function foldSplitLines(OrgPartner $orgPartner, LengthAwarePaginator $paginator): void
    {
        $rootOf = $paginator->getCollection()->mapWithKeys(fn ($row) => [$row->id => $row->id]);
        $pieces = collect();
        $parentIds = $rootOf->keys();

        while ($parentIds->isNotEmpty()) {
            $children = $this->linesQuery(PartnerShoppingListItem::query(), $orgPartner)
                ->whereIn('partner_shopping_list_items.parent_id', $parentIds)
                ->whereIn('partner_shopping_list_items.state', $this->statesInView())
                ->get();

            foreach ($children as $child) {
                $rootOf[$child->id] = $rootOf[$child->parent_id];
                $pieces->push($child->setAttribute('root_id', $rootOf[$child->id]));
            }

            $parentIds = $children->pluck('id');
        }

        $this->loadProductionSteps($paginator->getCollection()->concat($pieces));
        $piecesByRoot = $pieces->groupBy('root_id');

        $paginator->getCollection()->transform(function ($row) use ($piecesByRoot) {
            $rowPieces = $piecesByRoot->get($row->id);

            if (!$rowPieces) {
                return $row;
            }

            $row->progress_parts = collect([$row])->concat($rowPieces)
                ->map(fn ($piece) => array_merge($this->progressOf($piece), ['quantity' => (float) $piece->quantity]))
                ->groupBy(fn ($part) => $part['label'].'|'.$part['reference'])
                ->map(fn ($parts) => array_merge($parts->first(), ['quantity' => $parts->sum('quantity')]))
                ->values()
                ->all();
            $row->quantity = (float) $row->quantity + $rowPieces->sum(fn ($piece) => (float) $piece->quantity);
            $row->folded_ids = $rowPieces->pluck('id')->all();

            return $row;
        });
    }

    private function linesQuery(Builder|QueryBuilder $query, OrgPartner $orgPartner): Builder|QueryBuilder
    {
        return $query
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
                'partner_shopping_list_items.parent_id',
                'partner_shopping_list_items.quantity',
                'partner_shopping_list_items.priority',
                'partner_shopping_list_items.state',
                'partner_shopping_list_items.needed_by',
                'partner_shopping_list_items.notes',
                'partner_shopping_list_items.created_at',
                'partner_shopping_list_items.pre_picked_at',
                'partner_shopping_list_items.preparing_at',
                'partner_shopping_list_items.job_order_id',
                'partner_shopping_list_items.transaction_id',
                'partner_shopping_list_items.poked_at',
                'partner_shopping_list_items.suggested_by_hub',
                'partner_shopping_list_items.dismiss_reason',
                'partner_shopping_list_items.dismissed_at',
                'partner_shopping_list_items.org_stock_id',
                'org_stocks.code as org_stock_code',
                'org_stocks.name as org_stock_name',
                'org_stocks.quantity_available as buyer_available',
                'users.contact_name as added_by_name',
                'org_stock_stats.days_of_cover',
                'partner_org_stocks.quantity_available as their_available',
                DB::raw('(select stocks.units_per_carton::numeric / greatest(coalesce(partner_org_stocks.packed_in, 1), 1) from stocks where stocks.id = partner_shopping_list_items.stock_id) as skos_per_carton'),
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
            ->selectRaw(PartnerShoppingListItem::pricePerSkoSql(GetPartnerSellingShopIds::run($orgPartner->partner)).' as price_per_sko');
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

        if ($this->productionSteps->isEmpty()) {
            $this->loadProductionSteps($paginator->getCollection());
        }

        $paginator->getCollection()->transform(function ($row) use ($orgStocks, $exchange, $quarterlyUsage, $stockDeliveries, $leadTimeDays) {
            $orgStock  = $orgStocks->get($row->org_stock_id);
            $tradeUnit = $orgStock?->tradeUnits->first(fn ($tradeUnit) => $tradeUnit->image_id !== null);
            $packedIn  = (float) ($orgStock?->packed_in ?: 1);

            $row->image_sources            = $tradeUnit?->imageSources(160, 160);
            $row->price_per_sko            = $row->price_per_sko === null ? null : round((float) $row->price_per_sko * $exchange, 4);
            $row->progress                 = $this->progressOf($row);
            $row->is_editable              = $row->state === ShoppingListItemStateEnum::DRAFT || (empty($row->folded_ids) && $row->isWaitingForPartner());
            $row->can_be_poked             = $this->isSentView && $row->canBePoked();
            $row->is_recently_poked        = $row->poked_at?->gt(now()->subHour()) ?? false;
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
            $skos = (float) $sentSkos->get($row->org_stock_id, collect())->whereNotIn('id', [$row->id, ...($row->folded_ids ?? [])])->sum('quantity');

            if ($skos > 0) {
                $packedIn = (float) ($orgStocks->get($row->org_stock_id)?->packed_in ?: 1);

                $row->other_open_purchase_orders = collect($row->other_open_purchase_orders ?? [])->push([
                    'slug'             => null,
                    'reference'        => __('from :partner', ['partner' => $partnerName]),
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
        return array_merge($this->stageOf($row), ['details' => $this->detailsOf($row)]);
    }

    /**
     * @return array{label: string, tone: string, reference: string|null}
     */
    private function stageOf(object $row): array
    {
        if ($row->state === ShoppingListItemStateEnum::DRAFT) {
            return ['label' => __('Not sent'), 'tone' => 'gray', 'reference' => null];
        }

        if ($row->state === ShoppingListItemStateEnum::DISMISSED) {
            return ['label' => __('Cannot be made'), 'tone' => 'red', 'reference' => null];
        }

        if ($row->delivery_note_reference) {
            return $row->delivery_note_state === 'dispatched'
                ? ['label' => __('On its way'), 'tone' => 'emerald', 'reference' => $row->delivery_note_reference]
                : ['label' => __('Being picked'), 'tone' => 'indigo', 'reference' => $row->delivery_note_reference];
        }

        if ((float) $row->quantity_staged > 0) {
            return ['label' => __('Ready to ship'), 'tone' => 'emerald', 'reference' => $row->order_reference];
        }

        if ($row->order_reference) {
            return ['label' => __('Pre-picked'), 'tone' => 'indigo', 'reference' => $row->order_reference];
        }

        if ($row->job_order_reference) {
            $steps = $this->productionSteps->get($row->id, collect());

            return $steps->isNotEmpty() && $steps->every(fn ($step) => $step['made'] >= $step['required'])
                ? ['label' => __('Made'), 'tone' => 'emerald', 'reference' => $row->job_order_reference]
                : ['label' => __('Being made'), 'tone' => 'amber', 'reference' => $row->job_order_reference];
        }

        if ($row->pre_picked_at) {
            return ['label' => __('Picked from stock'), 'tone' => 'indigo', 'reference' => null];
        }

        if ($row->preparing_at) {
            return ['label' => __('Queued to be made'), 'tone' => 'amber', 'reference' => null];
        }

        return ['label' => __('Waiting for the partner'), 'tone' => 'gray', 'reference' => null];
    }

    /**
     * What happened to the line so far, oldest first, so the buyer sees who is doing what and since when.
     *
     * @return array<int, array{label: string, at: string|null}>
     */
    private function detailsOf(object $row): array
    {
        $details = [['label' => __('Requested'), 'at' => $this->isoDate($row->created_at)]];

        if ($row->pre_picked_at) {
            $details[] = ['label' => __('Picked from stock'), 'at' => $this->isoDate($row->pre_picked_at)];
        }

        if ($row->preparing_at) {
            $details[] = ['label' => __('Sent to production'), 'at' => $this->isoDate($row->preparing_at)];
        }

        if ($row->dismissed_at) {
            $details[] = ['label' => __('Cannot be made: :reason', ['reason' => $row->dismiss_reason]), 'at' => $this->isoDate($row->dismissed_at)];
        }

        foreach ($this->productionSteps->get($row->id, collect()) as $step) {
            $done = $step['required'] > 0 ? (int) round(100 * min($step['made'], $step['required']) / $step['required']) : 0;

            $details[] = [
                'label' => trim($step['name'].' '.$done.'%'.($step['who'] ? ' · '.$step['who'] : '')),
                'at'    => $this->isoDate($step['last_at']),
            ];
        }

        if ((float) $row->quantity_staged > 0) {
            $details[] = ['label' => __(':quantity in the bay ready to ship', ['quantity' => trimDecimalZeros($row->quantity_staged)]), 'at' => null];
        }

        if ($row->delivery_note_reference) {
            $details[] = [
                'label' => $row->delivery_note_state === 'dispatched'
                    ? __('Dispatched :reference', ['reference' => $row->delivery_note_reference])
                    : __('Picking :reference', ['reference' => $row->delivery_note_reference]),
                'at'    => null,
            ];
        }

        return $details;
    }

    private function isoDate(mixed $at): ?string
    {
        return $at ? Carbon::parse($at)->toIso8601String() : null;
    }

    /**
     * The production steps of each line's job order item, with who worked on them and when they last did.
     *
     * @param Collection<int, object> $lines
     */
    private function loadProductionSteps(Collection $lines): void
    {
        $ids = $lines->filter(fn ($line) => $line->job_order_reference)->pluck('id')->unique()->values();

        $this->productionSteps = $ids->isEmpty() ? collect() : DB::table('partner_shopping_list_items as items')
            ->join('org_stocks as partner_org_stocks', function ($join) {
                $join->on('partner_org_stocks.stock_id', 'items.stock_id')
                    ->on('partner_org_stocks.organisation_id', 'items.partner_organisation_id');
            })
            ->join('artefacts', 'artefacts.org_stock_id', 'partner_org_stocks.id')
            ->join('job_order_items', function ($join) {
                $join->on('job_order_items.job_order_id', 'items.job_order_id')
                    ->on('job_order_items.artefact_id', 'artefacts.id');
            })
            ->join('job_order_item_tasks', 'job_order_item_tasks.job_order_item_id', 'job_order_items.id')
            ->leftJoin('manufacture_tasks', 'manufacture_tasks.id', 'job_order_item_tasks.manufacture_task_id')
            ->whereIn('items.id', $ids)
            ->whereNull('artefacts.deleted_at')
            ->whereNull('job_order_items.deleted_at')
            ->groupBy('items.id', 'job_order_item_tasks.manufacture_task_id', 'manufacture_tasks.name')
            ->select(['items.id as item_id', 'manufacture_tasks.name'])
            ->selectRaw('sum(job_order_item_tasks.quantity_made) as made, sum(job_order_item_tasks.quantity_required) as required, min(job_order_item_tasks.position) as position')
            ->selectRaw("(select string_agg(distinct employees.alias, ', ') from manufacture_task_sessions
                join employees on employees.id = manufacture_task_sessions.employee_id
                where manufacture_task_sessions.job_order_item_task_id = any(array_agg(job_order_item_tasks.id))) as who")
            ->selectRaw('(select max(coalesce(manufacture_task_sessions.ended_at, manufacture_task_sessions.started_at)) from manufacture_task_sessions
                where manufacture_task_sessions.job_order_item_task_id = any(array_agg(job_order_item_tasks.id))) as last_at')
            ->orderBy('position')
            ->get()
            ->groupBy('item_id')
            ->map(fn (Collection $steps) => $steps->map(fn ($step) => [
                'name'     => $step->name ?? __('Making'),
                'made'     => (float) $step->made,
                'required' => (float) $step->required,
                'who'      => $step->who,
                'last_at'  => $step->last_at,
            ])->values());
    }

    public function tableStructure(OrgPartner $orgPartner): Closure
    {
        return function (InertiaTable $table) use ($orgPartner) {
            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('line'), __('lines')]);

            $table
                ->withEmptyState([
                    'title' => $this->isSentView ? __('Nothing sent to the partner is open') : __('The ongoing PO is empty'),
                ])
                ->column(key: 'org_stock_code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'info', label: __('SKO description'), canBeHidden: false)
                ->column(key: 'quantity', label: __('SKOs'), canBeHidden: false, align: 'right')
                ->column(key: 'amount', label: __('Amount'), canBeHidden: false, align: 'right');

            foreach ($this->elementGroups($orgPartner) as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements'],
                );
            }

            if ($this->isSentView) {
                $table
                    ->column(key: 'progress', label: __('Progress'), canBeHidden: false);
            }

            $table->column(key: 'created_at', label: __('Added'), canBeHidden: false, sortable: true);

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
                'hubSuggestionsCount' => PartnerShoppingListItem::where('org_partner_id', $this->orgPartner->id)
                    ->where('state', ShoppingListItemStateEnum::DRAFT)
                    ->where('suggested_by_hub', true)
                    ->count(),
                'partnerCode' => $this->orgPartner->partner->code,
                'filterGroups' => collect($this->filterGroups($this->orgPartner))->map(fn ($group, $key) => ['key' => $key, 'label' => $group['label'], 'options' => $group['options']])->values(),
                'data' => $items,
            ]
        )->table($this->tableStructure($this->orgPartner));
    }

    private function linesValue(OrgPartner $orgPartner): float
    {
        $value = DB::table('partner_shopping_list_items')
            ->where('org_partner_id', $orgPartner->id)
            ->whereIn('state', $this->statesInView())
            ->where('state', '!=', ShoppingListItemStateEnum::DISMISSED->value)
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
