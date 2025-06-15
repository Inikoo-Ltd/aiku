<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 15 Jun 2025 21:52:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\UI;

use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

trait WithShopsInOverview
{
    protected function getElementGroups(Group|Organisation $parent): array
    {
        return [
            'state' => [
                'label'    => __('State'),
                'elements' => array_merge_recursive(
                    ShopStateEnum::labels(),
                    ShopStateEnum::count($parent)
                ),

                'engine' => function ($query, $elements) {
                    $query->whereIn('shops.state', $elements);
                }
            ],
        ];
    }

    public function handle(Group|Organisation $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('shops.name', $value)
                    ->orWhereStartWith('shops.code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Shop::class);
        $queryBuilder->leftJoin('organisations', 'organisations.id', '=', 'shops.organisation_id');
        $queryBuilder->where('shops.type', '!=', ShopTypeEnum::FULFILMENT);

        if ($parent instanceof Group) {
            $queryBuilder->where('shops.group_id', $parent->id);
        } else {
            $queryBuilder->where('shops.organisation_id', $parent->id);
        }


        foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }

        return $queryBuilder
            ->defaultSort('shops.code')
            ->select([
                'shops.code',
                'shops.id',
                'shops.name',
                'shops.slug',
                'shops.type',
                'shops.state',
                'organisations.name as organisation_name',
                'organisations.slug as organisation_slug',
                'organisations.code as organisation_code',
            ])
            ->allowedSorts(['code', 'name', 'type', 'state', 'organisation_code'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Group|Organisation $parent, $prefix): \Closure
    {
        return function (InertiaTable $table) use ($prefix, $parent) {
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


            $emptyState = [
                'title'       => __('No shops found'),
                'description' => '',
                'count'       => $parent->catalogueStats->number_shops,
                'action'      => null

            ];


            $table
                ->withGlobalSearch()
                ->withModelOperations()
                ->withEmptyState($emptyState)
                ->column(key: 'state', label: '', canBeHidden: false, type: 'avatar');

            if ($parent instanceof Group) {
                $table->column(key: 'organisation_code', label: __('Org'), canBeHidden: false, sortable: true, searchable: true);
            }
            $table->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'type', label: __('type'), canBeHidden: false, sortable: true, searchable: true)
                ->defaultSort('code');
        };
    }
}
