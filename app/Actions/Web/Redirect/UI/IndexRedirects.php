<?php

/*
 * author Arya Permana - Kirin
 * created on 12-03-2025-11h-30m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Web\Redirect\UI;

use App\Actions\Dashboard\ShowOrganisationDashboard;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Web\Website\UI\ShowWebsite;
use App\Enums\UI\Web\Redirect\RedirectTabsEnum;
use App\Http\Resources\Web\RedirectsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Redirect;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexRedirects extends OrgAction
{
    use WithWebAuthorisation;
    
    private Website $website;

    public function htmlResponse(LengthAwarePaginator $redirects, ActionRequest $request): Response
    {
        return Inertia::render('Org/Web/Redirect/Redirects', [
            'title'       => __('Redirects'),
            'breadcrumbs' => $this->getBreadcrumbs(
                $request->route()->getName(),
                $request->route()->originalParameters()
            ),
            'pageHead'    => [
                'title'     => $this->website->name,
                'model'     => __('Redirects'),
                'icon'      => [
                    'title' => __('Redirects'),
                    'icon'  => 'fal fa-terminal'
                ],
                // 'iconRight' => $this->website->state->stateIcon()[$this->website->state->value],
                'actions'   =>[
                ]
            ],
            'tabs'        => [
                'current'    => $this->tab,
                'navigation' => RedirectTabsEnum::navigation()
            ],
            'route_redirects' => [
                'submit'              => [
                    'name'       => 'grp.models.website.redirect.store',
                    'parameters' => [
                        'organisation' => $this->shop->organisation->slug,
                        'shop'         => $this->shop->slug,
                        'website'      => $this->website->id
                    ]
                ],
                'fetch_live_webpages' => [
                    'name'       => 'grp.json.active_webpages.index',
                    'parameters' => [
                        'shop' => $this->shop->slug,
                    ]
                ],
            ],
            RedirectTabsEnum::REDIRECTS->value => $this->tab == RedirectTabsEnum::REDIRECTS->value ?
                RedirectsResource::collection($this->handle(parent: $this->website, prefix: RedirectTabsEnum::REDIRECTS->value))
                : Inertia::lazy(fn () => RedirectsResource::collection($this->handle(parent: $this->website, prefix: RedirectTabsEnum::REDIRECTS->value))),
        ])
        ->table(IndexRedirects::make()->tableStructure(parent: $this->website, prefix: RedirectTabsEnum::REDIRECTS->value));
    }

    public function handle(Website|Webpage $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('redirects.from_url', $value)
                    ->orWhereStartWith('webpages.title', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Redirect::class);
        if ($parent instanceof Website) {
            $queryBuilder->where('redirects.website_id', $parent->id);
        } else {
            $queryBuilder->where('redirects.to_webpage_id', $parent->id);
        }

        $queryBuilder->leftjoin('webpages', 'redirects.to_webpage_id', '=', 'webpages.id');
        $queryBuilder->leftJoin('websites', 'webpages.website_id', '=', 'websites.id');

        $queryBuilder
            ->defaultSort('redirects.id')
            ->select([
                'redirects.id',
                'redirects.type',
                'redirects.from_url as url',
                'redirects.from_path as path',
                'webpages.title as to_webpage_title',
                'webpages.slug as to_webpage_slug',
                'webpages.url as to_webpage_url',
                'webpages.code as to_webpage_code',
                'webpages.slug as to_webpage_slug',
                'websites.domain as to_website_domain',
                'websites.slug as to_website_slug',
            ]);

        return $queryBuilder
            ->allowedSorts(['url', 'type', 'to_webpage_url'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(
        Website|Webpage $parent,
        ?array $modelOperations = null,
        $prefix = null,
    ): Closure {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix . 'Page');
            }

            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                    match (class_basename($parent)) {
                        'Website', 'Webpage' => [
                            'title'       => __("No redirects found"),
                        ],
                        default => null
                    }
                );

            $table
                ->column(key: 'type', label: __('Type'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'url', label: __('From URL'), canBeHidden: false, sortable: true, searchable: true);

            if ($parent instanceof Website) {
                $table
                    ->column(key: 'to_webpage_url', label: __('To Webpage'), canBeHidden: false, sortable: true, searchable: true);
                $table->column(key: 'actions_from_website', label: '', canBeHidden: false, sortable: false, searchable: true);
            }
        };
    }
    
    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, Website $website, ActionRequest $request): LengthAwarePaginator
    {
        $this->website = $website;
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(RedirectTabsEnum::values());

        return $this->handle($website);
    }

    public function asController(Organisation $organisation, Shop $shop, Website $website, ActionRequest $request): LengthAwarePaginator
    {
        $this->website = $website;
        $this->initialisationFromShop($shop, $request)->withTab(RedirectTabsEnum::values());

        return $this->handle($website);
    }

    public function jsonResponse(LengthAwarePaginator $redirects): AnonymousResourceCollection
    {
        return RedirectsResource::collection($redirects);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, ?string $suffix = null): array
    {
        $headCrumb = function (array $routeParameters, ?string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Redirects'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ],
            ];
        };

        // dd($routeName);
        switch ($routeName) {
            case 'grp.org.fulfilments.show.web.redirect.index':
                /** @var Website $website */
                $website = request()->route()->parameter('website');

                return array_merge(
                    ShowWebsite::make()->getBreadcrumbs(
                        $website,
                        'grp.org.fulfilments.show.web.websites.show',
                        $routeParameters
                    ),
                    $headCrumb(
                        [
                            'name'       => 'grp.org.fulfilments.show.web.redirect.index',
                            'parameters' => $routeParameters
                        ],
                        $suffix
                    )
                );
            case 'grp.org.shops.show.web.redirect.index':
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
                            'name'       => 'grp.org.shops.show.web.redirect.index',
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
