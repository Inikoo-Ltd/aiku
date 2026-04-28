<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Mon, 21 Apr 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Dispatching\BatchCode\UI;

use App\Actions\Inventory\UI\ShowInventoryDashboard;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithInventoryAuthorisation;
use App\Http\Resources\Dispatching\BatchCodeResource;
use App\InertiaTable\InertiaTable;
use App\Models\Dispatching\BatchCode;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexBatchCodes extends OrgAction
{
    use WithInventoryAuthorisation;

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($organisation);
    }

    public function handle(Organisation $organisation, ?string $prefix = null): LengthAwarePaginator
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

        return QueryBuilder::for(BatchCode::class)
            ->where('batch_codes.organisation_id', $organisation->id)
            ->leftJoin('org_stocks', 'batch_codes.org_stock_id', '=', 'org_stocks.id')
            ->defaultSort('batch_codes.code')
            ->select([
                'batch_codes.id',
                'batch_codes.code',
                'batch_codes.expiry_date',
                'batch_codes.number_delivery_notes',
                'org_stocks.id as org_stock_id',
                'org_stocks.code as org_stock_code',
                'org_stocks.name as org_stock_name',
                'org_stocks.slug as org_stock_slug',
            ])
            ->allowedSorts(['code', 'expiry_date', 'org_stock_code', 'number_delivery_notes'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?array $modelOperations = null, ?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table
                ->defaultSort('code')
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(['title' => __('No batch codes found')])
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'expiry_date', label: __('Expiry Date'), canBeHidden: false, sortable: true, type: 'date')
                ->column(key: 'org_stock_code', label: __('SKU'), canBeHidden: false, sortable: true)
                ->column(key: 'number_delivery_notes', label: __('Delivery Notes'), canBeHidden: false, sortable: true)
                ->column(key: 'actions', label: '', canBeHidden: false, align: 'right');
        };
    }

    public function jsonResponse(LengthAwarePaginator $batchCodes): AnonymousResourceCollection
    {
        return BatchCodeResource::collection($batchCodes);
    }

    public function htmlResponse(LengthAwarePaginator $batchCodes, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Inventory/BatchCodes',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Batch Codes'),
                'pageHead'    => [
                    'title'     => __('Batch Codes'),
                    'icon'      => ['icon' => ['fal', 'fa-barcode'], 'title' => __('Batch Codes')],
                    'model'     => __('Warehouse'),
                    'actions'   => [
                        [
                            'type'   => 'buttonGroup',
                            'key'    => 'upload-add',
                            'button' => [
                                app()->isLocal() ? [
                                    'type'  => 'button',
                                    'style' => 'primary',
                                    'icon'  => ['fal', 'fa-upload'],
                                    'label' => __('Upload'),
                                    'route' => [
                                        'name'       => 'grp.models.warehouse.batch_codes.upload',
                                        'parameters' => [$this->warehouse->id],
                                    ],
                                ] : [],
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
                'upload_batch_codes' => [
                    'title' => [
                        'label'       => __('Upload Batch Codes'),
                        'information' => __('The list of column file:'),
                    ],
                    'progressDescription' => __('Importing batch codes'),
                    'preview_template'    => [
                        'header' => ['code', 'expiry_date', 'sku'],
                        'rows'   => [
                            [
                                'code'        => 'BC-001',
                                'expiry_date' => '2027-12-31',
                                'sku'         => 'SKU-001',
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
                'data' => BatchCodeResource::collection($batchCodes),
            ]
        )->table($this->tableStructure());
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
}
