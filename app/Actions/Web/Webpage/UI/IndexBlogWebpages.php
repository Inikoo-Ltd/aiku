<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Feb 2024 14:48:23 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage\UI;

use App\Actions\Iris\Blog\IndexIrisBlogs;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Web\Website\UI\ShowWebsite;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Http\Resources\Web\WebpagesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class IndexBlogWebpages extends OrgAction
{
    use WithWebAuthorisation;

    private Group|Organisation|Website|Fulfilment|Webpage $parent;

    private mixed $bucket;

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(Organisation $organisation, Shop $shop, Website $website, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'blog';
        $this->parent = $website;
        $this->initialisationFromShop($website->shop, $request);


        return $this->handle(parent: $this->parent, bucket: $this->bucket);
    }

    protected function getElementGroups(Organisation|Website|Webpage $parent): array
    {
        return [
            'state' => [
                'label'    => __('State'),
                'elements' => array_merge_recursive(
                    WebpageStateEnum::labels(),
                    WebpageStateEnum::count($parent)
                ),

                'engine' => function ($query, $elements) {
                    $query->whereIn('webpages.state', $elements);
                }

            ],

        ];
    }


    public function handle(Group|Organisation|Website|Webpage $parent, $prefix = null, $bucket = null): LengthAwarePaginator
    {
        if ($bucket) {
            $this->bucket = $bucket;
        }

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('webpages.code', $value)
                    ->orWhereStartWith('webpages.url', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Webpage::class);

        if (!($parent instanceof Group)) {
            foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
                $queryBuilder->whereElementGroup(
                    key: $key,
                    allowedElements: array_keys($elementGroup['elements']),
                    engine: $elementGroup['engine'],
                    prefix: $prefix
                );
            }
        }


        if ($parent instanceof Organisation) {
            $queryBuilder->where('webpages.organisation_id', $parent->id);
        } elseif ($parent instanceof Webpage) {
            $queryBuilder->where('webpages.parent_id', $parent->id);
        } elseif ($parent instanceof Group) {
            $queryBuilder->where('webpages.group_id', $parent->id);
        } else {
            $queryBuilder->where('webpages.website_id', $parent->id);
        }

        $queryBuilder->where('webpages.type', WebpageTypeEnum::BLOG);

        if (isset(request()->query()['json']) && request()->query()['json'] === 'true' || (function_exists('request') && request() && request()->expectsJson())) {
            $queryBuilder->orderByRaw(
                "CASE
            WHEN webpages.sub_type = 'storefront' THEN 1
            WHEN webpages.sub_type = 'department' THEN 2
            WHEN webpages.sub_type = 'sub_department' THEN 3
            WHEN webpages.sub_type = 'family' THEN 4
            WHEN webpages.sub_type = 'catalogue' THEN 5
            WHEN webpages.sub_type = 'product' THEN 6
            ELSE 7
            END, webpages.sub_type ASC"
            );
        }

        $queryBuilder->leftJoin('organisations', 'webpages.organisation_id', '=', 'organisations.id');
        $queryBuilder->leftJoin('shops', 'webpages.shop_id', '=', 'shops.id');
        $queryBuilder->leftJoin('websites', 'webpages.website_id', '=', 'websites.id');
        $queryBuilder->leftJoin('snapshots as draft_snapshots', 'webpages.unpublished_snapshot_id', '=', 'draft_snapshots.id');
        $queryBuilder->leftJoin('users as draft_editors', function ($join) {
            $join->on('draft_snapshots.publisher_id', '=', 'draft_editors.id')
                ->where('draft_snapshots.publisher_type', 'User');
        });
        $queryBuilder->leftJoin('snapshots as live_snapshots', 'webpages.live_snapshot_id', '=', 'live_snapshots.id');
        $queryBuilder->leftJoin('users as live_publishers', function ($join) {
            $join->on('live_snapshots.publisher_id', '=', 'live_publishers.id')
                ->where('live_snapshots.publisher_type', 'User');
        });

        $publishedAtExpression  = IndexIrisBlogs::make()->getPublishedAtSortExpression();
        $lastEditedByExpression = $this->lastEditedBySqlExpression();

        return $queryBuilder
            ->defaultSort('webpages.level')
            ->select([
                'webpages.code',
                'webpages.id',
                'webpages.type',
                'webpages.slug',
                'webpages.level',
                'webpages.state',
                'webpages.sub_type',
                'webpages.url',
                'organisations.slug as organisation_slug',
                'shops.slug as shop_slug',
                'shops.name as shop_name',
                'organisations.name as organisation_name',
                'websites.domain as website_url',
                'websites.slug as website_slug',
                DB::raw("$publishedAtExpression as published_at"),
                DB::raw("$lastEditedByExpression as last_edited_by"),
            ])
            ->allowedSorts([
                'code',
                'type',
                'level',
                'url',
                'state',
                AllowedSort::callback('published_at', fn ($query, bool $descending) => $query->orderByRaw($publishedAtExpression.' '.($descending ? 'desc' : 'asc').' nulls last')),
                AllowedSort::callback('last_edited_by', fn ($query, bool $descending) => $query->orderByRaw($lastEditedByExpression.' '.($descending ? 'desc' : 'asc').' nulls last')),
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()?->getName())
            ->withQueryString();
    }

    /**
     * Who last changed the blog: the editor of its draft, recorded since content edits keep it, else
     * whoever published the live version, else the last person in its audit trail (title, url, state).
     */
    private function lastEditedBySqlExpression(): string
    {
        return "COALESCE(
            draft_editors.contact_name,
            live_publishers.contact_name,
            (SELECT audit_users.contact_name FROM audits
                JOIN users AS audit_users ON audit_users.id = audits.user_id
                WHERE audits.auditable_type = 'Webpage' AND audits.auditable_id = webpages.id AND audits.user_type = 'User'
                ORDER BY audits.id DESC LIMIT 1)
        )";
    }

    public function tableStructure(Group|Organisation|Website|Webpage $parent, ?array $modelOperations = null, $prefix = null, string $bucket = ''): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix, $bucket) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            if (!($parent instanceof Group)) {
                foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
                    $table->elementGroup(
                        key: $key,
                        label: $elementGroup['label'],
                        elements: $elementGroup['elements']
                    );
                }
            }


            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                    match (class_basename($parent)) {
                        'Organisation' => [
                            'title'       => __("No webpages found"),
                            'description' => $parent->webStats->number_websites == 0 ? __('Nor any website exist 🤭') : null,
                            'count'       => $parent->webStats->number_webpages,

                        ],
                        'Website', 'Group' => [
                            'title' => __("No webpages found"),
                            'count' => $parent->webStats->number_webpages,
                        ],
                        default => null
                    }
                )
                ->column(key: 'level', label: '', icon: 'fal fa-sort-amount-down-alt', tooltip: __('Level'), canBeHidden: false, sortable: true, type: 'icon');
            $table->column(key: 'state', label: ['fal', 'fa-yin-yang'], tooltip: __('State'), sortable: true, type: 'icon');
            if ($bucket == 'all') {
                $table->column(key: 'type', label: '', icon: 'fal fa-shapes', tooltip: __('Type'), canBeHidden: false, type: 'icon');
            }
            $table->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'url', label: __('url'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'sub_type', label: __('Category'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'published_at', label: __('Published'), tooltip: __('The date the blog shows as published on the website'), sortable: true)
                ->column(key: 'last_edited_by', label: __('Last edited by'), tooltip: __('Who last changed the content, published or not'), sortable: true);
            if ($parent instanceof Group) {
                $table->column(key: 'organisation_name', label: __('organisation'), canBeHidden: false, sortable: true, searchable: true)
                    ->column(key: 'shop_name', label: __('Shop'), canBeHidden: false, sortable: true, searchable: true);
            }
            $table->defaultSort('level');
        };
    }

    public function jsonResponse(LengthAwarePaginator $webpages): AnonymousResourceCollection
    {
        return WebpagesResource::collection($webpages);
    }

    public function htmlResponse(LengthAwarePaginator $webpages, ActionRequest $request): Response
    {
        $subNavigation = [];


        $routeName = $request->route()->getName();

        $routeCreate = null;
        if (str_starts_with($routeName, 'grp.org.fulfilments.')) {
            $routeCreate = 'grp.org.fulfilments.show.web.webpages.create';
        } elseif (str_starts_with($routeName, 'grp.org.shops.show.web.')) {
            $routeCreate = 'grp.org.shops.show.web.blogs.create';
        }

        $actions = [];

        if ($routeCreate) {
            $actions[] = [
                'type'  => 'button',
                'style' => 'create',
                'label' => __('Blog'),
                'route' => [
                    'name'       => $routeCreate,
                    'parameters' => array_values($request->route()->originalParameters())
                ],
            ];
        }

        /** @var Website $website */
        $website = request()->route()->parameter('website');

        if ($this->canEdit && $website) {
            $actions[] = [
                'key'   => 'bulk-blog-category',
                'type'  => 'button',
                'label' => __('Bulk Category'),
            ];
        }

        return Inertia::render(
            'Org/Web/Webpages',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $routeName,
                    $request->route()->originalParameters()
                ),
                'title'       => __('Webpages'),
                'pageHead'    => [
                    'model'         => strtoupper($website?->code ?? '') . ' ' . __('Webpages'),
                    'title'         => ucfirst($this->bucket),
                    'color'         => '#f59e0b',
                    'icon'          => [
                        'icon'  => ['fal', 'fa-browser'],
                        'title' => __('Webpage')
                    ],
                    'subNavigation' => $subNavigation,
                    'actions'       => $actions,
                ],
                'data'        => WebpagesResource::collection($webpages),
                'blog_categories' => WebpageSubTypeEnum::blogCategoriesWithLabel($website?->shop?->type),
                'routes_list'     => [
                    'bulk_blog_category' => [
                        'method'     => 'patch',
                        'name'       => 'grp.models.webpage.set_blog_category_bulk',
                        'parameters' => [
                            'website' => $website?->id
                        ]
                    ],
                ],
            ]
        )->table($this->tableStructure(parent: $this->parent, bucket: $this->bucket));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, ?string $suffix = null): array
    {
        $headCrumb = function (array $routeParameters, ?string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Blogs'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ],
            ];
        };

        switch ($routeName) {
            case 'grp.org.shops.show.web.blogs.index':
                /** @var Website $website */
                $website = request()->route()->parameter('website');

                return array_merge(
                    ShowWebsite::make()->getBreadcrumbs(
                        $website,
                        'grp.org.shops.show.web.websites.show',
                        $routeParameters
                    ),
                    $headCrumb(
                        [
                            'name'       => 'grp.org.shops.show.web.blogs.index',
                            'parameters' => $routeParameters
                        ],
                        $suffix
                    )
                );
            default:
                return [];
        }
    }
}
