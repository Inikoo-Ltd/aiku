<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Sep 2023 18:42:14 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Announcement\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Web\Website\UI\ShowWebsite;
use App\Http\Resources\Web\BannersResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Banner;
use App\Models\Web\Website;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexAnnouncements extends OrgAction
{
    use WithWebAuthorisation;

    protected array $elementGroups = [];
    private Shop $parent;


    public function handle(Shop $parent, $prefix = null)
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where('banners.name', "%$value%");
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Banner::class)
            ->leftJoin('organisations', 'banners.organisation_id', '=', 'organisations.id')
            ->leftJoin('shops', 'banners.shop_id', '=', 'shops.id');

        $queryBuilder->where('banners.shop_id', $parent->id);
        $queryBuilder->select(
            'banners.id',
            'banners.slug',
            'banners.state',
            'banners.name',
            'banners.image_id',
            'banners.date',
            'shops.name as shop_name',
            'shops.slug as shop_slug',
            'organisations.name as organisation_name',
            'organisations.slug as organisation_slug',
        );


        return $queryBuilder
            ->defaultSort('-date')
            ->allowedSorts(['name', 'date', 'number_views', 'organisation_name', 'shop_name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }


    public function tableStructure(
        Shop $parent,
        ?array $modelOperations = null,
        $prefix = null,
        ?array $exportLinks = null
    ): Closure {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix, $exportLinks) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }


            $action = null;

            $description = null;

            $emptyState = [
                'title'       => __('No banners found'),
                'count'       => 0,
                'description' => $description,
                'action'      => $action
            ];


            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch()
                ->withEmptyState($emptyState)
                ->withExportLinks($exportLinks)
                ->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon')
                ->column(key: 'name', label: __('name'), sortable: true)
                ->defaultSort('-id');
        };
    }

    public function asController(Organisation $organisation, Shop $shop, Website $website, ActionRequest $request)
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($this->parent);
    }

    public function htmlResponse(LengthAwarePaginator $announcements, ActionRequest $request): Response
    {
        $container = null;

        $actions = null;

        return Inertia::render(
            'Websites/Announcements',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('announcements'),
                'pageHead'    => [
                    'title'     => __('announcements'),
                    'container' => $container,
                    'iconRight' => [
                        'title' => __('announcements'),
                        'icon'  => 'fal fa-sign'
                    ],
                    'actions'   => $actions,


                ],

                'data' => BannersResource::collection($announcements),
            ]
        )->table(
            $this->tableStructure(
                parent: $this->parent,
            )
        );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Banners'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ];
        };

        switch ($routeName) {
            case 'grp.org.shops.show.web.announcements.index':
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
                            'name'       => 'grp.org.shops.show.web.announcements.index',
                            'parameters' => $routeParameters
                        ]
                    ),
                );
            default:
                return [];
        }
    }
}
