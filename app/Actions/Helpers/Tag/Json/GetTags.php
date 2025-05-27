<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 26-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Helpers\Tag\Json;

use App\Actions\OrgAction;
use App\Enums\Helpers\Tag\TagScopeEnum;
use App\Http\Resources\Catalogue\TagsResource;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Tag;
use App\Services\QueryBuilder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetTags extends OrgAction
{
    public function handle(TradeUnit $parent, $prefix = null)
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('tags.name', $value);
            });
        });

        $queryBuilder = QueryBuilder::for(Tag::class);

        if ($parent instanceof TradeUnit) {
            $queryBuilder->where('tags.scope', TagScopeEnum::PRODUCT_PROPERTY);
        } else {
            $queryBuilder->where('tags.scope', TagScopeEnum::OTHER);
        }

        $queryBuilder
            ->leftJoin('groups', 'tags.group_id', '=', 'groups.id')
            ->leftJoin('organisations', 'tags.organisation_id', '=', 'organisations.id')
            ->leftJoin('shops', 'tags.shop_id', '=', 'shops.id');

        $queryBuilder
            ->defaultSort('tags.id')
            ->select([
                'tags.id',
                'tags.name',
                'tags.slug',
                'organisations.name as organisation_name',
                'organisations.slug as organisation_slug',
                'groups.name as group_name',
                'groups.slug as group_slug',
                'shops.name as shop_name',
                'shops.slug as shop_slug',
                'tags.created_at as tag_created_at',
            ]);

        return $queryBuilder->allowedSorts(['tag_name'])
            ->allowedFilters([$globalSearch])->get();
    }

    public function jsonResponse($tags): AnonymousResourceCollection
    {
        return TagsResource::collection($tags);
    }

    public function inTradeUnit(TradeUnit $tradeUnit, ActionRequest $request)
    {
        $this->initialisationFromGroup($tradeUnit->group, $request);

        return $this->handle($tradeUnit);
    }

}
