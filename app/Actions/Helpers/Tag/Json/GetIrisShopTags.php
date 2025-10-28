<?php

/*
 * author Arya Permana - Kirin
 * created on 01-07-2025-17h-51m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Helpers\Tag\Json;

use App\Actions\IrisAction;
use App\Enums\Helpers\Tag\TagScopeEnum;
use App\Http\Resources\Catalogue\TagsResource;
use App\Models\Helpers\Tag;
use App\Services\QueryBuilder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetIrisShopTags extends IrisAction
{
    public function handle()
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('tags.name', $value);
            });
        });
        $perPage = 250;
        $queryBuilder = QueryBuilder::for(Tag::class);

        $queryBuilder->where('tags.scope', TagScopeEnum::PRODUCT_PROPERTY);
        $queryBuilder->join('model_has_tags', 'tags.id', '=', 'model_has_tags.tag_id')
            ->where('model_has_tags.shop_id', $this->shop->id);

        $queryBuilder
            ->defaultSort('tags.id')
            ->select([
                'tags.id',
                'tags.name',
                'tags.slug',
                'tags.created_at',
            ])->distinct();

        return $queryBuilder->defaultSort('name')
            ->allowedSorts(['name', 'created_at'])
            ->allowedFilters([$globalSearch])
            ->withIrisPaginator($perPage)
            ->withQueryString();
    }

    public function jsonResponse($tags): AnonymousResourceCollection
    {
        return TagsResource::collection($tags);
    }

    public function asController(ActionRequest $request)
    {
        $this->initialisation($request);

        return $this->handle();
    }

}
