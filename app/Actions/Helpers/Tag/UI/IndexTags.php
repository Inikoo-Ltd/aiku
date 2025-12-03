<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 26-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
*/

namespace App\Actions\Helpers\Tag\UI;

use App\Actions\OrgAction;
use App\Enums\Helpers\Tag\TagScopeEnum;
use App\Http\Resources\Catalogue\TagsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Tag;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexTags extends OrgAction
{
    public function inTradeUnit(TradeUnit $tradeUnit, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromGroup($tradeUnit->group, $request);

        return $this->handle($tradeUnit);
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function handle(Organisation|TradeUnit $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query
                    ->whereStartWith('name', $value)
                    ->orWhereWith('scope', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Tag::class);

        if ($parent instanceof TradeUnit) {
            $queryBuilder->where('scope', TagScopeEnum::PRODUCT_PROPERTY);
        }

        return $queryBuilder
            ->defaultSort('name')
            ->select(['id', 'name', 'slug', 'scope'])
            ->allowedSorts(['name', 'scope'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch()
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'scope', label: __('Scope'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'action', label: __('Action'))
                ->defaultSort('name');
        };
    }

    public function jsonResponse(LengthAwarePaginator $tags): AnonymousResourceCollection
    {
        return TagsResource::collection($tags);
    }
}
