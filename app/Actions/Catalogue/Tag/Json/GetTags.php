<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 26-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Catalogue\Tag\Json;

use App\Actions\OrgAction;
use App\Http\Resources\Catalogue\TagsResource;
use App\Models\Catalogue\Tag;
use App\Models\Goods\TradeUnit;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetTags extends OrgAction
{
    public function handle(TradeUnit $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('products.name', $value)
                    ->orWhereStartWith('products.code', $value);
            });
        });

        $queryBuilder = QueryBuilder::for(Tag::class);

        $queryBuilder->join('model_has_tags', 'model_has_tags.tag_id', '=', 'tags.id')
                ->where('model_has_tags.model_type', class_basename($parent))
                ->where('model_has_tags.model_id', $parent->id);

        $queryBuilder
            ->leftJoin('groups', 'tags.group_id', '=', 'groups.id')
            ->leftJoin('organisations', 'tags.organisation_id', '=', 'organisations.id')
            ->leftJoin('shops', 'tags.shop_id', '=', 'shops.id');

        $queryBuilder
            ->defaultSort('model_has_tags.id')
            ->select([
                'model_has_tags.id',
                'tags.id as tags_id',
                'tags.name as tags_name',
                'tags.slug as tags_slug',
                'organisations.name as organisation_name',
                'organisations.slug as organisation_slug',
                'groups.name as group_name',
                'groups.slug as group_slug',
                'shops.name as shop_name',
                'shops.slug as shop_slug',
                'tags.created_at as tags_created_at',
            ]);

        return $queryBuilder->allowedSorts(['tags_name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $tags): AnonymousResourceCollection
    {
        return TagsResource::collection($tags);
    }

    public function inTradeUnit(TradeUnit $tradeUnit, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromGroup($tradeUnit->group, $request);

        return $this->handle($tradeUnit);
    }

}
