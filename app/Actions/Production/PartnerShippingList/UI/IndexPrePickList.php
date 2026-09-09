<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 09 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\PartnerShippingList\UI;

use App\Actions\OrgAction;
use App\Actions\Production\Production\UI\ShowProduction;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexPrePickList extends OrgAction
{
    private const CATEGORY = "coalesce(artefact_departments.name, '')";

    private ?array $elementGroups = null;


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

    public function handle(Organisation $seller, ?int $perPage = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('stocks.code', $value)
                    ->orWhereStartWith('stocks.name', $value)
                    ->orWhereStartWith('organisations.code', $value);
            });
        });

        $queryBuilder = QueryBuilder::for(PartnerShoppingListItem::class)
            ->join('stocks', 'stocks.id', 'partner_shopping_list_items.stock_id')
            ->join('org_stocks', function ($join) use ($seller) {
                $join->on('org_stocks.stock_id', 'stocks.id')
                    ->where('org_stocks.organisation_id', $seller->id);
            })
            ->leftJoin('artefacts', function ($join) {
                $join->on('artefacts.org_stock_id', 'org_stocks.id')
                    ->where('artefacts.production_id', $this->production->id)
                    ->whereNull('artefacts.deleted_at');
            })
            ->leftJoin('artefact_departments', 'artefacts.artefact_department_id', 'artefact_departments.id')
            ->join('organisations', 'organisations.id', 'partner_shopping_list_items.organisation_id')
            ->where('partner_shopping_list_items.partner_organisation_id', $seller->id)
            ->where('partner_shopping_list_items.state', ShoppingListItemStateEnum::OPEN)
            ->where('org_stocks.quantity_available', '>', 0);

        foreach ($this->getElementGroups() as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine']
            );
        }

        return $queryBuilder
            ->select([
                'partner_shopping_list_items.id',
                'partner_shopping_list_items.quantity',
                'partner_shopping_list_items.priority',
                'partner_shopping_list_items.needed_by',
                'partner_shopping_list_items.created_at',
                'org_stocks.quantity_available as stock_available',
                'stocks.code as stock_code',
                'stocks.name as stock_name',
                'organisations.code as buyer_code',
                DB::raw(self::CATEGORY.' as category'),
                DB::raw('least(partner_shopping_list_items.quantity, org_stocks.quantity_available) as can_pick'),
            ])
            ->defaultSort('stock_code')
            ->allowedFilters([$globalSearch])
            ->allowedSorts(['stock_code', 'buyer_code', 'quantity', 'stock_available', 'can_pick', 'priority', 'needed_by', 'created_at'])
            ->withPaginator(null, $perPage, tableName: request()->route()->getName())
            ->withQueryString();
    }

    /**
     * Lines with stock behind them, capped at what is actually available, honouring the active filters.
     *
     * @return array<int, array{id: int, quantity: float}>
     */
    public function eligibleLines(Organisation $seller): array
    {
        return collect($this->handle($seller, 10000)->items())
            ->map(fn ($row) => ['id' => (int) $row->id, 'quantity' => (float) $row->can_pick])
            ->all();
    }

    /** @return array<string, array{label: string, elements: array<string, array{0: string, 1: int}>, engine: Closure}> */
    public function getElementGroups(): array
    {
        if ($this->elementGroups !== null) {
            return $this->elementGroups;
        }

        $counts = fn (string $expression) => PartnerShoppingListItem::query()
            ->join('stocks', 'stocks.id', 'partner_shopping_list_items.stock_id')
            ->join('org_stocks', function ($join) {
                $join->on('org_stocks.stock_id', 'stocks.id')
                    ->where('org_stocks.organisation_id', $this->organisation->id);
            })
            ->leftJoin('artefacts', function ($join) {
                $join->on('artefacts.org_stock_id', 'org_stocks.id')
                    ->where('artefacts.production_id', $this->production->id)
                    ->whereNull('artefacts.deleted_at');
            })
            ->leftJoin('artefact_departments', 'artefacts.artefact_department_id', 'artefact_departments.id')
            ->join('organisations', 'organisations.id', 'partner_shopping_list_items.organisation_id')
            ->where('partner_shopping_list_items.partner_organisation_id', $this->organisation->id)
            ->where('partner_shopping_list_items.state', ShoppingListItemStateEnum::OPEN)
            ->where('org_stocks.quantity_available', '>', 0)
            ->selectRaw("$expression as element, count(*) as total")
            ->groupBy('element')
            ->orderByDesc('total')
            ->pluck('total', 'element')
            ->filter(fn ($total, $element) => $element !== '' && $element !== null)
            ->all();

        $group = fn (string $label, string $expression, array $elements) => [
            'label'    => $label,
            'elements' => collect($elements)->mapWithKeys(fn ($total, $element) => [$element => [__($element), $total]])->all(),
            'engine'   => function ($query, $elements) use ($expression) {
                $query->whereIn(DB::raw($expression), $elements);
            },
        ];

        return $this->elementGroups = [
            'category'  => $group(__('Category'), self::CATEGORY, $counts(self::CATEGORY)),
            'requester' => $group(__('Requester'), 'organisations.code', $counts('organisations.code')),
            'priority'  => $group(__('Urgency'), 'partner_shopping_list_items.priority', $counts('partner_shopping_list_items.priority')),
        ];
    }

    public function tableStructure(): Closure
    {
        return function (InertiaTable $table) {
            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('Line'), __('Lines')])
                ->withEmptyState([
                    'title' => __('Nothing can be pre-picked'),
                    'description' => __('A line shows up here when there is stock to send to the partner gathering location'),
                ])
                ->column(key: 'pick', label: '', canBeHidden: false)
                ->column(key: 'buyer_code', label: __('For'), canBeHidden: false, sortable: true)
                ->column(key: 'stock_code', label: __('Artefact'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'quantity', label: __('Asked'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'stock_available', label: __('In stock'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'can_pick', label: __('Can pick'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'priority', label: __('Priority'), canBeHidden: false, sortable: true)
                ->column(key: 'created_at', label: __('Added'), canBeHidden: false, sortable: true)
                ->column(key: 'action', label: '', canBeHidden: false, align: 'right')
                ->defaultSort('stock_code');
        };
    }

    public function asController(Organisation $organisation, Production $production, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($organisation);
    }

    public function htmlResponse(LengthAwarePaginator $items, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Production/PrePickList',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Pre-pick'),
                'pageHead'    => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-hand-holding-box'],
                        'title' => __('Pre-pick'),
                    ],
                    'title' => __('Pre-pick'),
                ],
                'filters'      => collect($this->getElementGroups())
                    ->map(fn ($group) => [
                        'label'   => $group['label'],
                        'options' => collect($group['elements'])->map(fn ($element, $value) => ['value' => $value, 'label' => $element[0], 'count' => $element[1]])->values()->all(),
                    ])
                    ->all(),
                'data'         => $items,
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
                            'name'       => 'grp.org.productions.show.pre_pick.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Pre-pick'),
                        'icon'  => 'fal fa-hand-holding-box',
                    ],
                ],
            ]
        );
    }
}
