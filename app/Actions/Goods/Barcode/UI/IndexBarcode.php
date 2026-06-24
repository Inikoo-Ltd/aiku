<?php

/*
 * Author Louis Perez
 * Created on 19-06-2026-09h-46m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\Barcode\UI;

use App\Actions\Goods\TradeUnit\UI\ShowTradeUnitsDashboard;
use App\Actions\GrpAction;
use App\Enums\Helpers\Barcode\BarcodeStatusEnum;
use App\Enums\UI\Goods\BarcodesTabsEnum;
use App\Http\Resources\Goods\BarcodesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Helpers\Barcode;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexBarcode extends GrpAction
{
    private Group $parent;

    public function handle(Group $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('barcodes.number', $value)
                    ->orWhereAnyWordStartsWith('barcodes.notes', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Barcode::class);
        $queryBuilder->where('group_id', $parent->id);

        foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }

        $queryBuilder->with('tradeUnitActive');

        return $queryBuilder
            ->select([
                'id',
                'number',
                'slug',
                'type',
                'status',
                'note',
            ])
            ->allowedSorts([
                'number',
                'note',
                'status'
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Group $parent, string $prefix)
    {
        return function (InertiaTable $table) use ($parent, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements']
                );
            }

            $emptyState = match (class_basename($parent)) {
                'Group' => [
                    'title' => __("No Barcode found"),
                ],
                default => null
            };

            $table
                ->withGlobalSearch()
                ->withEmptyState($emptyState);

            $table
                ->column(key: 'status', label: ['fal', 'fa-yin-yang'], type: 'icon')
                ->column(key: 'number', label: __('Barcode Number'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'type', label: __('Type'), canBeHidden: false, sortable: true, searchable: false)
                ->column(key: 'note', label: __('Note'), className: 'w-2/5', canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'trade_units', label: __('Trade Units'), canBeHidden: false);
        };
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation(group(), $request)->withTab(BarcodesTabsEnum::values());
        $this->parent = $this->group;

        return $this->handle($this->parent);
    }

    public function jsonResponse(LengthAwarePaginator $barcodes): AnonymousResourceCollection
    {
        return BarcodesResource::collection($barcodes);
    }

    public function htmlResponse(LengthAwarePaginator $barcodes, ActionRequest $request): Response
    {
        $title = 'Barcodes';

        return Inertia::render('Goods/Barcodes', [
            'breadcrumbs'                  => $this->getBreadcrumbs(
                $request->route()->getName(),
                $request->route()->originalParameters()
            ),
            'title'                        => $title,
            'pageHead'                     => [
                'title'         => $title,
                'iconRight'     => [
                    'icon'  => ['fal', 'fa-barcode'],
                    'title' => $title,
                ],
                'actions'       => [

                ]
            ],
            'tabs'        => [
                'current'    => $this->tab,
                'navigation' => BarcodesTabsEnum::navigation(),
            ],
            BarcodesTabsEnum::INDEX->value => $this->tab == BarcodesTabsEnum::INDEX->value
                    ? fn () => $this->jsonResponse($this->handle(group(), BarcodesTabsEnum::INDEX->value))
                    : Inertia::lazy(fn () => $this->jsonResponse($this->handle(group(), BarcodesTabsEnum::INDEX->value))),
        ])
        ->table($this->tableStructure($this->parent, prefix: BarcodesTabsEnum::INDEX->value));
    }

    protected function getElementGroups(Group $parent): array
    {
        return [
            'status' => [
                'label'    => __('Status'),
                'elements' => array_merge_recursive(
                    BarcodeStatusEnum::labels(),
                    BarcodeStatusEnum::count($parent)
                ),
                'engine' => function ($query, $elements) {
                    $query->whereIn('status', $elements);
                }

            ],
        ];
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, ?string $suffix = null): array
    {
        $headCrumb = function (array $routeParameters, ?string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Barcodes'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ]
            ];
        };

        return array_merge(
            ShowTradeUnitsDashboard::make()->getBreadcrumbs(),
            $headCrumb(
                [
                    'name'       => $routeName,
                    'parameters' => $routeParameters
                ],
                $suffix
            )
        );
    }
}
