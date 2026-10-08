<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Mon, 21 Apr 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Dispatching\BatchCode\UI;

use App\Actions\Inventory\OrgStock\UI\ShowOrgStock;
use App\Actions\Inventory\UI\ShowInventoryDashboard;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithInventoryAuthorisation;
use App\Http\Resources\Dispatching\BatchCodeResource;
use App\InertiaTable\InertiaTable;
use App\Models\Dispatching\BatchCode;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexBatchCodes extends OrgAction
{
    use WithInventoryAuthorisation;

    private ?OrgStock $orgStock = null;

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromWarehouse($warehouse, $request);
        $this->orgStock = null;

        return $this->handle($organisation);
    }

    public function inOrgStock(Organisation $organisation, Warehouse $warehouse, OrgStock $orgStock, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromWarehouse($warehouse, $request);
        $this->orgStock = $orgStock;

        return $this->handle($organisation, $orgStock);
    }

    public function handle(Organisation $organisation, ?OrgStock $orgStock = null, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('batch_codes.code', $value)
                    ->orWhereStartWith('org_stocks.code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(BatchCode::class)
            ->where('batch_codes.organisation_id', $organisation->id)
            ->when($orgStock, fn ($query) => $query->where('batch_codes.org_stock_id', $orgStock->id))
            ->leftJoin('org_stocks', 'batch_codes.org_stock_id', '=', 'org_stocks.id')
            ->leftJoinSub(self::onHandQuery(), 'on_hand', 'on_hand.batch_code_id', '=', 'batch_codes.id');

        foreach ($this->getElementGroups() as $key => $elementGroup) {
            $query->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix,
                default: $elementGroup['default'],
            );
        }

        return $query
            ->defaultSort('expiry_date')
            ->select([
                'batch_codes.id',
                'batch_codes.code',
                'batch_codes.expiry_date',
                'batch_codes.number_delivery_notes',
                'org_stocks.id as org_stock_id',
                'org_stocks.code as org_stock_code',
                'org_stocks.name as org_stock_name',
                'org_stocks.slug as org_stock_slug',
                DB::raw('coalesce(on_hand.quantity, 0) as quantity_on_hand'),
                DB::raw('coalesce(on_hand.number_locations, 0) as number_locations'),
            ])
            ->allowedSorts(['code', 'expiry_date', 'org_stock_code', 'number_delivery_notes', 'quantity_on_hand', 'number_locations'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    /**
     * SKOs of each batch on the shelves now and in how many locations, read off the stock movements.
     */
    public static function onHandQuery(): \Illuminate\Database\Query\Builder
    {
        $perLocation = DB::table('org_stock_movement_batches')
            ->groupBy('batch_code_id', 'location_id')
            ->havingRaw('sum(quantity) > 0.000001')
            ->selectRaw('batch_code_id, sum(quantity) as quantity');

        return DB::query()->fromSub($perLocation, 'per_location')
            ->groupBy('batch_code_id')
            ->selectRaw('batch_code_id, sum(quantity) as quantity, count(*) as number_locations');
    }

    /**
     * Each location of the SKO with what it holds and which batches the stock says are there, for counting it batch by batch.
     */
    private function batchCount(OrgStock $orgStock): array
    {
        $batches = DB::table('org_stock_movement_batches')
            ->join('batch_codes', 'batch_codes.id', '=', 'org_stock_movement_batches.batch_code_id')
            ->where('org_stock_movement_batches.org_stock_id', $orgStock->id)
            ->groupBy('org_stock_movement_batches.location_id', 'batch_codes.id', 'batch_codes.code', 'batch_codes.expiry_date')
            ->havingRaw('sum(org_stock_movement_batches.quantity) > 0.000001')
            ->orderByRaw('batch_codes.expiry_date asc nulls last, batch_codes.id')
            ->selectRaw('org_stock_movement_batches.location_id, batch_codes.id as batch_code_id, batch_codes.code, batch_codes.expiry_date, sum(org_stock_movement_batches.quantity) as quantity')
            ->get()
            ->groupBy('location_id');

        return [
            'locations' => DB::table('location_org_stocks')
                ->join('locations', 'locations.id', '=', 'location_org_stocks.location_id')
                ->where('location_org_stocks.org_stock_id', $orgStock->id)
                ->where('location_org_stocks.warehouse_id', $this->warehouse->id)
                ->orderBy('locations.code')
                ->get(['location_org_stocks.id', 'location_org_stocks.location_id', 'locations.code', 'location_org_stocks.quantity'])
                ->map(fn ($location) => [
                    'location_org_stock_id' => $location->id,
                    'code'                  => $location->code,
                    'quantity'              => (float) $location->quantity,
                    'batches'               => $batches->get($location->location_id, collect())->map(fn ($batch) => [
                        'batch_code_id' => $batch->batch_code_id,
                        'code'          => $batch->code,
                        'expiry_date'   => $batch->expiry_date,
                        'quantity'      => (float) $batch->quantity,
                    ])->values()->all(),
                ])->all(),
            'batch_codes' => $orgStock->batchCodes()->orderByDesc('id')->limit(200)->get(['id', 'code', 'expiry_date'])->map(fn ($batchCode) => [
                'batch_code_id' => $batchCode->id,
                'code'          => $batchCode->code,
                'expiry_date'   => $batchCode->expiry_date?->toDateString(),
            ])->all(),
        ];
    }

    protected function getElementGroups(): array
    {
        return [
            'stock' => [
                'label'    => __('Stock'),
                'default'  => 'on_hand',
                'elements' => [
                    'on_hand' => [__('On the shelves')],
                    'empty'   => [__('None left')],
                ],
                'engine'   => function ($query, $elements) {
                    if (in_array('on_hand', $elements)) {
                        $query->whereNotNull('on_hand.batch_code_id');
                    } else {
                        $query->whereNull('on_hand.batch_code_id');
                    }
                },
            ],
        ];
    }

    public function tableStructure(bool $showOrgStockColumn = true, ?array $modelOperations = null, ?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($showOrgStockColumn, $modelOperations, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            foreach ($this->getElementGroups() as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements'],
                    default: $elementGroup['default'],
                );
            }

            $table
                ->defaultSort('expiry_date')
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(['title' => __('No batch codes found')])
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'expiry_date', label: __('Best before'), canBeHidden: false, sortable: true, type: 'date');

            if ($showOrgStockColumn) {
                $table->column(key: 'org_stock_code', label: __('SKO'), canBeHidden: false, sortable: true);
            }

            $table->column(key: 'quantity_on_hand', label: __('On the shelves'), canBeHidden: false, sortable: true, type: 'number');
            $table->column(key: 'number_locations', label: __('Locations'), canBeHidden: false, sortable: true, type: 'number');

            $table->column(key: 'number_delivery_notes', label: __('Delivery Notes'), canBeHidden: false, sortable: true);
            $table->column(key: 'actions', label: '', canBeHidden: false, align: 'right');
        };
    }

    public function jsonResponse(LengthAwarePaginator $batchCodes): AnonymousResourceCollection
    {
        return BatchCodeResource::collection($batchCodes);
    }

    public function htmlResponse(LengthAwarePaginator $batchCodes, ActionRequest $request): Response
    {
        $orgStock = $request->route()->parameter('orgStock');

        return Inertia::render(
            'Org/Inventory/BatchCodes',
            [
                'breadcrumbs' => $orgStock
                    ? $this->getOrgStockBreadcrumbs($orgStock, $request)
                    : $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Batch Codes'),
                'pageHead'    => [
                    'title'     => __('Batch Codes'),
                    'icon'      => ['icon' => ['fal', 'fa-barcode'], 'title' => __('Batch Codes')],
                    'model'     => $orgStock ? __('SKO') : __('Warehouse'),
                    'actions'   => $orgStock ? [
                        [
                            'type'  => 'button',
                            'style' => 'create',
                            'label' => __('Batch Code'),
                            'route' => [
                                'name'       => $request->route()->getName().'.create',
                                'parameters' => $request->route()->originalParameters(),
                            ],
                        ],
                    ] : [
                        [
                            'type'   => 'buttonGroup',
                            'key'    => 'upload-add',
                            'button' => [
                                [
                                    'type'  => 'button',
                                    'style' => 'primary',
                                    'icon'  => ['fal', 'fa-upload'],
                                    'label' => __('Upload'),
                                    'route' => [
                                        'name'       => 'grp.models.warehouse.batch_codes.upload',
                                        'parameters' => [$this->warehouse->id],
                                    ],
                                ],
                                [
                                    'type'  => 'button',
                                    'style' => 'create',
                                    'label' => __('Batch Code'),
                                    'route' => [
                                        'name'       => 'grp.org.warehouses.show.inventory.batch_codes.create',
                                        'parameters' => $request->route()->originalParameters(),
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'upload_batch_codes' => $orgStock ? null : [
                    'title' => [
                        'label'       => __('Upload Batch Codes'),
                        'information' => __('The list of column file: code, expiry_date, sku'),
                    ],
                    'progressDescription' => __('Importing batch codes'),
                    'preview_template'    => [
                        'header' => ['code', 'expiry_date', 'sku'],
                        'rows'   => [
                            [
                                'code'        => 'BC-001',
                                'expiry_date' => '2027-12-31',
                                'sku'         => 'SKO-001',
                            ],
                        ],
                    ],
                    'upload_spreadsheet' => [
                        'event'           => 'action-progress',
                        'channel'         => 'grp.personal.'.$request->user()->id,
                        'required_fields' => ['code', 'expiry_date', 'sku'],
                        'template'        => [
                            'label' => 'Download template (.xlsx)',
                        ],
                        'route' => [
                            'upload' => [
                                'name'       => 'grp.models.warehouse.batch_codes.upload',
                                'parameters' => [$this->warehouse->id],
                            ],
                        ],
                    ],
                ],
                'allow_edit' => !$orgStock,
                'batch_count' => $orgStock ? $this->batchCount($orgStock) : null,
                'data' => BatchCodeResource::collection($batchCodes),
            ]
        )->table($this->tableStructure(!$orgStock));
    }

    public function getBreadcrumbs(array $routeParameters, ?string $suffix = null): array
    {
        return array_merge(
            ShowInventoryDashboard::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route'  => [
                            'name'       => 'grp.org.warehouses.show.inventory.batch_codes.index',
                            'parameters' => $routeParameters,
                        ],
                        'label'  => __('Batch Codes'),
                        'icon'   => 'fal fa-bars',
                    ],
                    'suffix' => $suffix,
                ],
            ]
        );
    }

    public function getOrgStockBreadcrumbs(OrgStock $orgStock, ActionRequest $request): array
    {
        $routeName = preg_replace('/\.batch_codes$/', '', $request->route()->getName());

        return ShowOrgStock::make()->getBreadcrumbs(
            $orgStock,
            $routeName,
            $request->route()->originalParameters(),
            '('.__('Batch Codes').')'
        );
    }
}
