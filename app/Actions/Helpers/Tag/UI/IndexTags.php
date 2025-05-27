<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 26-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Helpers\Tag\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithGoodsAuthorisation;
use App\Enums\Helpers\Tag\TagScopeEnum;
use App\Http\Resources\Catalogue\TagsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Tag;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexTags extends OrgAction
{
    use WithGoodsAuthorisation;

    public function inTradeUnit(TradeUnit $tradeUnit, Tag $tag, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromGroup($tag->group, $request);

        return $this->handle($tradeUnit, $tag);
    }


    public function handle(TradeUnit $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('tags.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

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
            ->defaultSort('tags_name')
            ->select([
                'model_has_tags.id',
                'tags.id as tags_id',
                'tags.name as tags_name',
                'tags.slug as tags_slug',
                'tags.scope as tags_scope',
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

    public function tableStructure(?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->defaultSort('tags_name')
                ->withGlobalSearch()
                ->dateInterval($this->dateInterval)
                ->withModelOperations($modelOperations)
                ->column(key: 'tags_name', label: __('name'), canBeHidden: false, sortable: true, searchable: true);
        };
    }


    public function jsonResponse(LengthAwarePaginator $tags): AnonymousResourceCollection
    {
        return TagsResource::collection($tags);
    }


    public function htmlResponse(LengthAwarePaginator $tags, ActionRequest $request): Response
    {
        return Inertia::render(
            'Devel/Dummy',
            [

                'title'    => __('Tags'),
                'pageHead' => [
                    'title'     => __('Tags'),
                    'iconRight' => [
                        'icon'  => ['fal', 'fa-box'],
                        'title' => __('Tags')
                    ],
                    'actions'   => [
                        $this->canEdit ? [
                            'type'    => 'button',
                            'style'   => 'create',
                            'tooltip' => __('new Tag'),
                            'label'   => __('Tag'),
                            'route'   => [
                                'name'       => 'grp.goods.tags.create',
                                'parameters' => []
                            ]
                        ] : false,
                    ],
                ],
                'data'     => TagsResource::collection($tags),

            ]
        )->table($this->tableStructure());
    }

}
