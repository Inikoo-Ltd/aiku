<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026 17:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\PackingBench\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithDispatchingEquipmentAuthorisation;
use App\Actions\UI\Dispatch\ShowDispatchHub;
use App\InertiaTable\InertiaTable;
use App\Models\Dispatching\PackingBench;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexPackingBenches extends OrgAction
{
    use WithDispatchingEquipmentAuthorisation;

    public function handle(Warehouse $warehouse, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->whereStartWith('packing_benches.code', $value);
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(PackingBench::class)
            ->where('packing_benches.warehouse_id', $warehouse->id)
            ->select([
                'packing_benches.id',
                'packing_benches.code',
                'packing_benches.slug',
                'packing_benches.status',
            ])
            ->defaultSort('packing_benches.code')
            ->allowedSorts(['code', 'status'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }
            $table
                ->withGlobalSearch()
                ->withEmptyState([
                    'title'       => __('No packing benches yet'),
                    'description' => $this->canEdit ? __('Add one per packing table, the packer picks it when they start packing') : null,
                    'count'       => 0,
                ])
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'status', label: __('Active'), canBeHidden: false, sortable: true)
                ->column(key: 'actions', label: '', canBeHidden: false)
                ->defaultSort('code');
        };
    }

    public function htmlResponse(LengthAwarePaginator $packingBenches, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Dispatching/PackingBenches',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Packing benches'),
                'pageHead'    => [
                    'title'   => __('Packing benches'),
                    'icon'    => ['fal', 'fa-video'],
                    'actions' => [
                        $this->canEdit ? [
                            'type'  => 'button',
                            'style' => 'create',
                            'label' => __('Create'),
                            'route' => [
                                'name'       => 'grp.org.warehouses.show.dispatching.packing_benches.create',
                                'parameters' => array_values($request->route()->originalParameters()),
                            ],
                        ] : false,
                    ],
                ],
                'canEdit'     => $this->canEdit,
                'data'        => $packingBenches->through(fn (PackingBench $packingBench) => [
                    'id'     => $packingBench->id,
                    'code'   => $packingBench->code,
                    'slug'   => $packingBench->slug,
                    'status' => $packingBench->status,
                ]),
            ]
        )->table($this->tableStructure());
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($warehouse);
    }

    public function getBreadcrumbs(array $routeParameters, ?string $suffix = null): array
    {
        return array_merge(
            ShowDispatchHub::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.dispatching.packing_benches.index',
                            'parameters' => [$routeParameters['organisation'], $routeParameters['warehouse']],
                        ],
                        'label' => __('Packing benches'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ],
            ]
        );
    }
}
