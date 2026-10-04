<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 10 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Restock\UI;

use App\Actions\Masters\MasterAsset\Json\GetMasterProductsPricingSales;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateTopCustomerShare;
use App\Actions\OrgAction;
use App\Actions\Production\PartnerShippingList\UI\IndexPartnerShippingList;
use App\Actions\Production\Production\UI\ShowProduction;
use App\Actions\Production\Restock\GetProductionLeadTime;
use App\Actions\Production\Restock\GetProductionStockCoverBuckets;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Inventory\OrgStock;
use App\Models\Production\Artefact;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class ShowToRestock extends OrgAction
{
    private const BUCKETS = ['out', 'w1', 'w2'];

    private const CATEGORY = "coalesce(ad.name, '')";

    private const SELLS_RANK = 'coalesce(s.sales_rank_12m, 4)';

    private const PACK = 'greatest(1, coalesce(os.packed_in, 1))';

    private const RAW_UNITS = 'ceil(round((coalesce(s.recommended_order_quantity, 0) * greatest(1, coalesce(os.packed_in, 1)))::numeric, 6))';

    private const IN_PRODUCTION_UNITS = 'coalesce(prod.skos, 0) * greatest(1, coalesce(os.packed_in, 1))';

    private const QUANTUM_UNITS = 'case when coalesce(a.recommended_batch_size, 0) > 0 then lcm(a.recommended_batch_size::bigint, greatest(1, coalesce(os.packed_in, 1))::bigint) else greatest(1, coalesce(os.packed_in, 1)) end';

    private const SUGGESTED_UNITS = "greatest(case when coalesce(a.recommended_batch_size, 0) > 0 then lcm(a.recommended_batch_size::bigint, greatest(1, coalesce(os.packed_in, 1))::bigint) else greatest(1, coalesce(os.packed_in, 1)) end,
        ceil(greatest(ceil(round((coalesce(s.recommended_order_quantity, 0) * greatest(1, coalesce(os.packed_in, 1)))::numeric, 6)) - coalesce(prod.skos, 0) * greatest(1, coalesce(os.packed_in, 1)), 0)
            / (case when coalesce(a.recommended_batch_size, 0) > 0 then lcm(a.recommended_batch_size::bigint, greatest(1, coalesce(os.packed_in, 1))::bigint) else greatest(1, coalesce(os.packed_in, 1)) end))
        * (case when coalesce(a.recommended_batch_size, 0) > 0 then lcm(a.recommended_batch_size::bigint, greatest(1, coalesce(os.packed_in, 1))::bigint) else greatest(1, coalesce(os.packed_in, 1)) end))";

    private const COVERED = '(prod.lines > 0 and coalesce(prod.skos, 0) * greatest(1, coalesce(os.packed_in, 1)) >= ceil(round((coalesce(s.recommended_order_quantity, 0) * greatest(1, coalesce(os.packed_in, 1)))::numeric, 6)))';

    private ?array $elementGroups = null;

    private ?int $leadDays = null;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            'productions-view.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.view",
            "productions_operations.{$this->production->id}.orchestrate",
            "productions_operations.{$this->production->id}.prepare",
            "productions_procurement.{$this->production->id}.view",
        ]);
    }

    private function leadDays(): int
    {
        return $this->leadDays ??= GetProductionLeadTime::run($this->production)['days'];
    }

    private function bucketExpression(): string
    {
        return GetProductionStockCoverBuckets::make()->bucketExpression($this->leadDays());
    }

    private function withoutPrivateCustomerStocks(object $query): object
    {
        $linked = "select 1 from product_has_org_stocks phos
            join products p on p.id = phos.product_id
            where phos.org_stock_id = os.id
                and p.deleted_at is null
                and p.state in ('".ProductStateEnum::ACTIVE->value."', '".ProductStateEnum::DISCONTINUING->value."')";

        return $query->whereRaw("(not exists ($linked) or exists ($linked
                and (p.exclusive_for_customer_id is null or p.exclusive_for_customer_id in (".OrgStockHydrateTopCustomerShare::partnerCustomersSql($this->organisation->id).'))))
            and not (coalesce(s.top_customer_dispatch_share, 0) >= 0.9 and s.top_customer_id is not null)');

    }

    private function boardQuery(): object
    {
        $leadDays = $this->leadDays();

        return DB::table('partner_shopping_list_items as sli')
            ->leftJoin('job_orders as jo', 'jo.id', 'sli.job_order_id')
            ->where('sli.state', ShoppingListItemStateEnum::OPEN->value)
            ->whereNull('sli.deleted_at')
            ->whereNull('sli.pre_picked_at')
            ->where(function ($query) {
                $query->whereNull('jo.id')->orWhere('jo.state', '!=', JobOrderStateEnum::RECEIVED->value);
            })
            ->where(function ($query) {
                $query->where('sli.partner_organisation_id', $this->organisation->id)
                    ->orWhere(function ($query) {
                        $query->whereNull('sli.partner_organisation_id')->where('sli.organisation_id', $this->organisation->id);
                    });
            })
            ->groupBy('sli.stock_id')
            ->selectRaw("sli.stock_id, count(*) as lines, count(sli.job_order_id) as on_floor,
                sum(coalesce(sli.quantity_to_produce, sli.quantity)) as skos,
                bool_or(sli.needed_by < current_date or sli.created_at < now() - interval '$leadDays days') as is_late,
                min(extract(day from now() - sli.created_at)) as age_min,
                max(extract(day from now() - sli.created_at)) as age_max");
    }

    private function candidatesQuery(): Builder
    {
        $query = GetProductionStockCoverBuckets::make()->scopedQuery(
            $this->production,
            Artefact::withoutGlobalScopes()->from('artefacts as a')
        );

        return $this->withoutPrivateCustomerStocks($query)
            ->leftJoin('artefact_departments as ad', 'ad.id', 'a.artefact_department_id')
            ->leftJoinSub($this->boardQuery(), 'prod', 'prod.stock_id', 'os.stock_id')
            ->whereNotNull('os.id')
            ->whereRaw('(coalesce(prod.skos, 0) = 0 or not '.self::COVERED.')');
    }

    public function handle(?int $perPage = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('os.code', $value)
                    ->orWhereStartWith('os.name', $value);
            });
        });

        $queryBuilder = QueryBuilder::for($this->candidatesQuery());

        foreach ($this->getElementGroups() as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                default: $elementGroup['default'] ?? null
            );
        }

        $items = $queryBuilder
            ->select([
                'a.id as artefact_id',
                'os.id as org_stock_id',
                'os.code as stock_code',
                'os.name as stock_name',
                'os.packed_in',
                'os.quantity_available as stock_available',
                's.days_of_cover',
                'a.recommended_batch_size as batch_size',
                DB::raw(self::CATEGORY.' as category'),
                DB::raw(self::SELLS_RANK.' as sells_rank'),
                DB::raw('('.self::SELLS_RANK.") * 1000000 + least(coalesce(s.days_of_cover, 0), 999999) as sells"),
                DB::raw(self::IN_PRODUCTION_UNITS.' as in_production'),
                DB::raw('coalesce(s.days_of_cover, 0) as lasts'),
                DB::raw(self::SUGGESTED_UNITS.' as job_units'),
                DB::raw(self::QUANTUM_UNITS.' as quantum_units'),
                DB::raw('coalesce(s.predicted_daily_usage, 0) * 365 * greatest(1, coalesce(os.packed_in, 1)) as annual_units'),
            ])
            ->defaultSort('sells')
            ->allowedFilters([$globalSearch])
            ->allowedSorts(['stock_code', 'sells', 'stock_available', 'lasts', 'job_units', 'in_production'])
            ->withPaginator(null, $perPage, tableName: request()->route()->getName())
            ->withQueryString();

        return $this->withSalesAndImages($items);
    }

    private function withSalesAndImages(LengthAwarePaginator $items): LengthAwarePaginator
    {
        $orgStockIds = $items->getCollection()->pluck('org_stock_id')->all();
        $periods     = GetMasterProductsPricingSales::periodKeys('year');

        $sales = $orgStockIds ? DB::table('org_stock_time_series as ts')
            ->join('org_stock_time_series_records as r', function ($join) use ($periods) {
                $join->on('r.org_stock_time_series_id', '=', 'ts.id')
                    ->whereIn('r.period', array_merge($periods['current'], $periods['previous_year']));
            })
            ->where('ts.frequency', $periods['frequency'])
            ->whereIn('ts.org_stock_id', $orgStockIds)
            ->groupBy('ts.org_stock_id')
            ->select('ts.org_stock_id')
            ->selectRaw('sum(case when r.period in ('.implode(',', array_fill(0, 12, '?')).') then r.sales_org_currency_external else 0 end) as sales', $periods['current'])
            ->selectRaw('sum(case when r.period in ('.implode(',', array_fill(0, 12, '?')).') then r.customers_invoiced else 0 end) as customers', $periods['current'])
            ->selectRaw('sum(case when r.period in ('.implode(',', array_fill(0, 12, '?')).') then r.sales_org_currency_external else 0 end) as sales_ly', $periods['previous_year'])
            ->get()
            ->keyBy('org_stock_id') : collect();

        $orgStocks = OrgStock::with('tradeUnits')->whereIn('id', $orgStockIds)->get()->keyBy('id');

        $items->getCollection()->transform(function ($row) use ($sales, $orgStocks) {
            $figures = $sales->get($row->org_stock_id);

            $row->sales         = $figures ? (float) $figures->sales : null;
            $row->customers     = $figures ? (int) $figures->customers : null;
            $row->sales_ly      = $figures ? (float) $figures->sales_ly : null;
            $row->currency_code = $this->organisation->currency->code;
            $row->image         = $orgStocks->get($row->org_stock_id)?->tradeUnits
                ->first(fn ($tradeUnit) => $tradeUnit->image_id !== null)?->imageSources(160, 160);

            return $row;
        });

        return $items;
    }

    /** @return array{cards: array<int, array<string, mixed>>, artisans: array<int, array<string, mixed>>} */
    private function sentCards(ActionRequest $request): array
    {
        $artefactIds = collect(explode(',', (string) $request->input('sent')))->filter(fn ($id) => ctype_digit($id))->map(fn ($id) => (int) $id)->all();

        $cards = $artefactIds ? DB::table('partner_shopping_list_items as sli')
            ->join('org_stocks as os', function ($join) {
                $join->on('os.stock_id', 'sli.stock_id')->where('os.organisation_id', $this->organisation->id);
            })
            ->join('artefacts as a', function ($join) {
                $join->on('a.org_stock_id', 'os.id')->where('a.production_id', $this->production->id)->whereNull('a.deleted_at');
            })
            ->leftJoin('artefact_departments as ad', 'ad.id', 'a.artefact_department_id')
            ->whereIn('a.id', $artefactIds)
            ->where('sli.state', ShoppingListItemStateEnum::OPEN->value)
            ->whereNull('sli.deleted_at')
            ->whereNull('sli.pre_picked_at')
            ->whereNull('sli.job_order_id')
            ->whereNull('sli.partner_organisation_id')
            ->where('sli.organisation_id', $this->organisation->id)
            ->orderBy('sli.id')
            ->get([
                'sli.id',
                'sli.quantity',
                'sli.quantity_to_produce',
                'sli.priority',
                'sli.preparing_at',
                'a.id as artefact_id',
                'os.code as stock_code',
                'os.name as stock_name',
                'os.quantity_available as stock_available',
                'ad.name as family',
            ])
            ->keyBy('artefact_id') : collect();

        $index = IndexPartnerShippingList::make();
        $index->initialisationFromProduction($this->production, []);

        return ['cards' => $cards->all(), 'artisans' => $cards->isEmpty() ? [] : $index->getArtisanWorkload()];
    }

    /** @return array<int, array<string, mixed>> */
    private function coverTable(): array
    {
        $leadDays = $this->leadDays();
        $covered  = self::COVERED;

        $rows = $this->withoutPrivateCustomerStocks(GetProductionStockCoverBuckets::make()->scopedQuery($this->production))
            ->leftJoinSub($this->boardQuery(), 'prod', 'prod.stock_id', 'os.stock_id')
            ->whereNotNull('os.id')
            ->groupByRaw($this->bucketExpression())
            ->selectRaw($this->bucketExpression()." as bucket,
                count(*) as total,
                count(*) filter (where $covered) as sent,
                count(*) filter (where $covered and prod.is_late) as late,
                count(*) filter (where $covered and prod.on_floor > 0) as on_floor,
                count(*) filter (where $covered and prod.on_floor = 0) as on_board,
                min(case when $covered then prod.age_min end) as age_min,
                max(case when $covered then prod.age_max end) as age_max")
            ->get()
            ->keyBy('bucket');

        $levels = collect(GetProductionStockCoverBuckets::BUCKETS)->except('never')->map(function ($meta, $bucket) use ($rows, $leadDays) {
            $row = $rows->get($bucket);

            return [
                'bucket'      => $bucket,
                'label'       => in_array($bucket, ['out', 'ok', 'dead'], true)
                    ? __($meta['label'])
                    : GetProductionStockCoverBuckets::make()->bucketLabel($bucket, $leadDays),
                'tone'        => $meta['tone'],
                'count'       => (int) ($row->total ?? 0),
                'in_production' => (int) (($row->sent ?? 0) - ($row->late ?? 0)),
                'late'        => (int) ($row->late ?? 0),
                'on_board'    => (int) ($row->on_board ?? 0),
                'on_floor'    => (int) ($row->on_floor ?? 0),
                'days_min'    => $row?->age_min === null ? null : (int) $row->age_min,
                'days_max'    => $row?->age_max === null ? null : (int) $row->age_max,
            ];
        })->values();

        return $levels->all();
    }

    /** @return array<string, string> */
    private function stateLabels(): array
    {
        return collect(GetProductionStockCoverBuckets::BUCKETS)->except('never')->map(
            fn ($meta, string $bucket) => in_array($bucket, ['out', 'ok', 'dead'], true)
                ? __($meta['label'])
                : GetProductionStockCoverBuckets::make()->bucketLabel($bucket, $this->leadDays())
        )->all();
    }

    /** @return array<string, array{label: string, elements: array<string, array{0: string, 1: int}>, engine: Closure, default?: string}> */
    public function getElementGroups(): array
    {
        if ($this->elementGroups !== null) {
            return $this->elementGroups;
        }

        $counts = fn (string $expression) => $this->candidatesQuery()
            ->selectRaw("$expression as element, count(*) as total")
            ->groupByRaw($expression)
            ->orderByDesc('total')
            ->pluck('total', 'element')
            ->filter(fn ($total, $element) => $element !== '' && $element !== null)
            ->all();

        $group = fn (string $label, string $expression, array $elements, array $labels = [], ?string $default = null, array $tones = [], array $tooltips = []) => [
            'label'    => $label,
            'elements' => collect($elements)->mapWithKeys(fn ($total, $element) => [(string) $element => [$labels[$element] ?? $element, $total, $tooltips[$element] ?? null, $tones[$element] ?? null]])->all(),
            'engine'   => function ($query, $elements) use ($expression) {
                $query->whereIn(DB::raw($expression), $elements);
            },
            'default'  => $default,
        ];

        $buckets     = collect(GetProductionStockCoverBuckets::BUCKETS)->except('never');
        $sellsLabels = ['1' => __('Top'), '2' => __('Good'), '3' => __('Slow'), '4' => __('Very slow')];
        $sellsTones  = ['1' => 'green', '2' => 'blue', '3' => 'amber', '4' => 'gray'];
        $sellsExpr   = self::SELLS_RANK.'::text';
        $sellsCounts = $counts($sellsExpr);
        $stateCounts = $counts($this->bucketExpression());
        $shortLabels = ['out' => __('Out'), 'w1' => __('Doomed'), 'w2' => __('Critical'), 'w3' => __('Danger'), 'w4' => __('Watch'), 'ok' => __('Covered'), 'dead' => __('Dead')];

        return $this->elementGroups = [
            'state'    => $group(__('Stock state'), $this->bucketExpression(), $buckets->map(fn ($meta, string $bucket) => $stateCounts[$bucket] ?? 0)->all(), $shortLabels, implode(',', self::BUCKETS), $buckets->map(fn ($meta) => $meta['tone'])->all(), $this->stateLabels()),
            'sells'    => $group(__('Sells'), $sellsExpr, collect($sellsLabels)->mapWithKeys(fn ($label, $rank) => [$rank => $sellsCounts[$rank] ?? 0])->all(), $sellsLabels, null, $sellsTones),
            'category' => $group(__('Category'), self::CATEGORY, $counts(self::CATEGORY)),
        ];
    }

    public function tableStructure(): Closure
    {
        return function (InertiaTable $table) {
            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('Product'), __('Products')])
                ->withEmptyState([
                    'title'       => __('Nothing needs making'),
                    'description' => __('Products show up here when they are out of stock or about to run out'),
                ])
                ->column(key: 'pick', label: '', canBeHidden: false)
                ->column(key: 'stock_code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'info', label: __('Info'), canBeHidden: false)
                ->column(key: 'sells', label: __('Sells'), canBeHidden: false, sortable: true)
                ->column(key: 'stock_available', label: __('In stock'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'lasts', label: __('Lasts'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'in_production', label: __('In production'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'job_units', label: __('Suggested'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'make', label: __('Make (units)'), canBeHidden: false, align: 'right')
                ->defaultSort('sells');
        };
    }

    public function asController(Organisation $organisation, Production $production, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle();
    }

    public function htmlResponse(LengthAwarePaginator $items, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Production/ToRestock',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('To restock'),
                'pageHead'    => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-inventory'],
                        'title' => __('To restock'),
                    ],
                    'title' => __('To restock'),
                ],
                'leadTime'   => GetProductionLeadTime::run($this->production),
                'cover'      => $this->coverTable(),
                'sent'       => Inertia::optional(fn () => $this->sentCards($request)),
                'filters'    => collect($this->getElementGroups())
                    ->map(fn ($group) => [
                        'label'   => $group['label'],
                        'default' => $group['default'] ?? null,
                        'options' => collect($group['elements'])->map(fn ($element, $value) => ['value' => (string) $value, 'label' => $element[0], 'count' => $element[1], 'tooltip' => $element[2] ?? null, 'tone' => $element[3] ?? null])->values()->all(),
                    ])
                    ->all(),
                'data'           => $items,
            ]
        )->table($this->tableStructure());
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowProduction::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.productions.show.to_restock.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('To restock'),
                        'icon'  => 'fal fa-inventory',
                    ],
                ],
            ]
        );
    }
}
