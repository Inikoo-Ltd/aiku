<?php

/*
 * author Arya Permana - Kirin
 * created on 26-06-2025-18h-05m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Web\Webpage\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Web\Webpage\WithWebpageSubNavigation;
use App\Actions\Web\Website\UI\ShowWebsite;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Http\Resources\Web\WebpagesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexFamilyWebpages extends OrgAction
{
    use WithWebAuthorisation;
    use WithWebpageSubNavigation;

    private Website $website;

    public function asController(Organisation $organisation, Shop $shop, Website $website, ActionRequest $request): LengthAwarePaginator
    {
        $this->website = $website;
        $this->initialisationFromShop($website->shop, $request);

        return $this->handle(parent: $website, bucket: $this->bucket);
    }

    protected function getElementGroups(Website $parent): array
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

    public function handle(Website $parent, $prefix = null, $bucket = null): LengthAwarePaginator
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

        foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }

        $queryBuilder->where('webpages.website_id', $parent->id);
        $queryBuilder->where('webpages.sub_type', WebpageSubTypeEnum::FAMILY);
        $queryBuilder->leftJoin('organisations', 'webpages.organisation_id', '=', 'organisations.id');
        $queryBuilder->leftJoin('shops', 'webpages.shop_id', '=', 'shops.id');
        $queryBuilder->leftJoin('websites', 'webpages.website_id', '=', 'websites.id');

        return $queryBuilder
            ->defaultSort('webpages.level')
            ->select([
                'webpages.code',
                'webpages.id',
                'webpages.type',
                'webpages.slug',
                'webpages.level',
                'webpages.sub_type',
                'webpages.url',
                'organisations.slug as organisation_slug',
                'shops.slug as shop_slug',
                'shops.name as shop_name',
                'organisations.name as organisation_name',
                'websites.domain as website_url',
                'websites.slug as website_slug'
            ])
            ->allowedSorts(['code', 'type', 'level', 'url'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Website $parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix) {
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
                    [
                            'title' => __("No webpages found"),
                            'count' => $parent->webStats->number_webpages,
                        ]
                )
                ->column(key: 'level', label: '', icon: 'fal fa-sort-amount-down-alt', tooltip: __('Level'), canBeHidden: false, sortable: true, type: 'icon');
            $table->column(key: 'type', label: '', icon: 'fal fa-shapes', tooltip: __('Type'), canBeHidden: false, type: 'icon');
            $table->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'url', label: __('url'), canBeHidden: false, sortable: true, searchable: true);
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


        $subNavigation = $this->getWebpageNavigation($this->website);

        $routeCreate = '';

        return Inertia::render(
            'Org/Web/Webpages',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('webpages'),
                'pageHead'    => [
                    'title'         => __('family webpages'),
                    'icon'          => [
                        'icon'  => ['fal', 'fa-browser'],
                        'title' => __('webpage')
                    ],
                    'subNavigation' => $subNavigation,
                    'actions'       => [
                        [
                            'type'  => 'button',
                            'style' => 'create',
                            'label' => __('webpage'),
                            'route' => [
                                'name'       => $routeCreate,
                                'parameters' => array_values($request->route()->originalParameters())
                            ],
                        ]
                    ]
                ],
                'data'        => WebpagesResource::collection($webpages),

            ]
        )->table($this->tableStructure(parent: $this->website));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, string $suffix = null): array
    {
        $headCrumb = function (array $routeParameters, ?string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Family Webpages'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.shops.show.web.webpages.index.sub_type.family' =>
            array_merge(
                ShowWebsite::make()->getBreadcrumbs(
                    'Shop',
                    $routeParameters
                ),
                $headCrumb(
                    [
                        'name'       => 'grp.org.shops.show.web.webpages.index.sub_type.family',
                        'parameters' => $routeParameters
                    ],
                    $suffix
                )
            ),
            default => []
        };
    }
}
