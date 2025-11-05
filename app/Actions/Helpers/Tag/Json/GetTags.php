<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 26-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
*/

namespace App\Actions\Helpers\Tag\Json;

use App\Actions\OrgAction;
use App\Enums\Helpers\Tag\TagScopeEnum;
use App\Http\Resources\Catalogue\TagsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Tag;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetTags extends OrgAction
{
    public function inTradeUnit(TradeUnit $tradeUnit, ActionRequest $request): Collection
    {
        $this->initialisationFromGroup($tradeUnit->group, $request);

        return $this->handle($tradeUnit);
    }

    public function handle(Group|Organisation|TradeUnit $parent, $prefix = null): Collection
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

        $queryBuilder
            ->defaultSort('name')
            ->select(['id', 'name', 'slug', 'scope']);

        return $queryBuilder
            ->allowedSorts(['tag_name'])
            ->allowedFilters([$globalSearch])
            ->get();
    }

    public function jsonResponse($tags): AnonymousResourceCollection
    {
        return TagsResource::collection($tags);
    }
}
