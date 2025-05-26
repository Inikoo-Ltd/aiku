<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 26-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Catalogue\Tag\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithGoodsAuthorisation;
use App\Http\Resources\Catalogue\TagsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Catalogue\Tag;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
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

    private Group $parent;


    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = group();
        $this->initialisationFromGroup($this->parent, $request);

        return $this->handle($this->parent);
    }


    public function handle(Group|Organisation|Shop $parent, $prefix = null): LengthAwarePaginator
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

        if ($parent instanceof Group) {
            $queryBuilder->where('tags.group_id', $parent->id);
        } elseif ($parent instanceof Organisation) {
            $queryBuilder->where('tags.organisation_id', $parent->id);
        } elseif ($parent instanceof Shop) {
            $queryBuilder->where('tags.shop_id', $parent->id);
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

    public function tableStructure(Group|Organisation|Shop $parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix) {
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
        // $subNavigation = $this->getStocksSubNavigation();

        // $title = match ($this->bucket) {
        //     'active' => __('Active SKUs'),
        //     'in_process' => __('In process SKUs'),
        //     'discontinuing' => __('Discontinuing SKUs'),
        //     'discontinued' => __('Discontinued SKUs'),
        //     default => __('SKUs')
        // };

        return Inertia::render(
            'Devel/Dummy',
            [
                // 'breadcrumbs' => $this->getBreadcrumbs(
                //     $request->route()->getName(),
                //     $request->route()->originalParameters()
                // ),
                'title'       => __('Tags'),
                'pageHead'    => [
                    'title'         => __('Tags'),
                    'iconRight'     => [
                        'icon'  => ['fal', 'fa-box'],
                        'title' => __('Tags')
                    ],
                    'actions'       => [
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
                'data'        => TagsResource::collection($tags),

            ]
        )->table($this->tableStructure(parent: $this->parent));
    }

}
