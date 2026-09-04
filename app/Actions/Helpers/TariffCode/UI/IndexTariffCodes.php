<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\TariffCode\UI;

use App\Actions\Goods\UI\ShowGoodsDashboard;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithGoodsAuthorisation;
use App\InertiaTable\InertiaTable;
use App\Models\Helpers\TariffCode;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexTariffCodes extends OrgAction
{
    use WithGoodsAuthorisation;

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle();
    }

    protected function getElementGroups(): array
    {
        return [
            'named' => [
                'label'    => __('Name'),
                'elements' => [
                    'named'   => [__('Named'), TariffCode::whereNotNull('name')->count()],
                    'unnamed' => [__('Unnamed'), TariffCode::whereNull('name')->count()],
                ],
                'engine'   => function ($query, $elements) {
                    if (count($elements) === 1) {
                        array_pop($elements) === 'named' ? $query->whereNotNull('tariff_codes.name') : $query->whereNull('tariff_codes.name');
                    }
                },
            ],
        ];
    }

    public function handle($prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->where('tariff_codes.hs_code', 'ilike', "$value%")
                    ->orWhere('tariff_codes.name', 'ilike', "%$value%")
                    ->orWhere('tariff_codes.description', 'ilike', "%$value%");
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(TariffCode::class);

        foreach ($this->getElementGroups() as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }

        return $queryBuilder
            ->defaultSort('tariff_codes.hs_code')
            ->select(['tariff_codes.id', 'tariff_codes.hs_code', 'tariff_codes.level', 'tariff_codes.description', 'tariff_codes.name'])
            ->allowedSorts(['hs_code', 'level', 'name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure($prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            foreach ($this->getElementGroups() as $key => $elementGroup) {
                $table->elementGroup(key: $key, label: $elementGroup['label'], elements: $elementGroup['elements']);
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState(['title' => __('No tariff codes')])
                ->column(key: 'hs_code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true, className: 'whitespace-nowrap font-mono')
                ->column(key: 'level', label: __('Digits'), sortable: true, align: 'right')
                ->column(key: 'description', label: __('Official description'), canBeHidden: false, searchable: true)
                ->column(key: 'name', label: __('Export name'), canBeHidden: false, sortable: true, searchable: true)
                ->defaultSort('hs_code');
        };
    }

    public function htmlResponse(LengthAwarePaginator $tariffCodes, ActionRequest $request): Response
    {
        return Inertia::render(
            'Goods/TariffCodes',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('Tariff codes'),
                'pageHead'    => [
                    'title' => __('Tariff codes'),
                    'icon'  => ['title' => __('Tariff codes'), 'icon' => 'fal fa-globe'],
                ],
                'canEdit'     => $this->canEdit,
                'data'        => $tariffCodes,
            ]
        )->table($this->tableStructure());
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(
            ShowGoodsDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => ['name' => 'grp.goods.tariff_codes.index'],
                        'label' => __('Tariff codes'),
                        'icon'  => 'fal fa-bars',
                    ],
                ]
            ]
        );
    }
}
