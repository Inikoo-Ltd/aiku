<?php
/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 18 Oct 2022 11:30:40 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Web\WebpageVariant;

use App\Actions\InertiaAction;
use App\Actions\Market\Shop\UI\ShowShop;
use App\Actions\UI\Dashboard\Dashboard;
use App\Enums\UI\TabsAbbreviationEnum;
use App\Http\Resources\Market\ShopResource;
use App\Http\Resources\Market\WebpageResource;
use App\InertiaTable\InertiaTable;
use App\Models\Web\WebpageVariant;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class IndexWebpageVariants extends InertiaAction
{
    public function handle(): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->where('webpages.code', 'LIKE', "%$value%")
                    ->orWhere('webpages.type', 'LIKE', "%$value%");
            });
        });
        InertiaTable::updateQueryBuilderParameters(TabsAbbreviationEnum::WEBPAGES->value);

        return QueryBuilder::for(WebpageVariant::class)
            ->defaultSort('webpages.code')
            ->select(['code', 'id', 'type', 'slug'])
            ->allowedSorts(['code', 'type'])
            ->allowedFilters([$globalSearch])
            ->paginate(
                perPage: $this->perPage ?? config('ui.table.records_per_page'),
                pageName: TabsAbbreviationEnum::WEBSITES->value.'Page'
            )

            ->withQueryString();
    }

    public function tableStructure($parent): Closure
    {
        return function (InertiaTable $table) use ($parent) {
            $table
                ->name(TabsAbbreviationEnum::WEBPAGES->value)
                ->pageName(TabsAbbreviationEnum::WEBPAGES->value.'Page');
            $table
                ->withGlobalSearch()
                ->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'type', label: __('type'), canBeHidden: false, sortable: true, searchable: true)
                ->defaultSort('code');
        };
    }

    public function authorize(ActionRequest $request): bool
    {
        return
            (
                $request->user()->tokenCan('root') or
                $request->user()->hasPermissionTo('websites.view')
            );
    }


    public function jsonResponse(): AnonymousResourceCollection
    {
        return ShopResource::collection($this->handle());
    }


    public function htmlResponse(LengthAwarePaginator $webpages, ActionRequest $request)
    {
        $parent = $request->route()->parameters() == [] ? app('currentTenant') : last($request->route()->parameters());

        return Inertia::render(
            'Web/Webpages',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->parameters
                ),
                'title'       => __('webpages'),
                'pageHead'    => [
                    'title' => __('webpages'),
                ],
                'data'        => WebpageResource::collection($webpages),

            ]
        )->table($this->tableStructure($parent));
    }


    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->routeName = $request->route()->getName();

        return $this->handle();
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('webpages'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'webpages.index' =>
            array_merge(
                Dashboard::make()->getBreadcrumbs(),
                $headCrumb(
                    [
                        'name' => 'webpages.index',
                        null
                    ]
                ),
            ),


            'shops.show.webpages.index' =>
            array_merge(
                (new ShowShop())->getBreadcrumbs($routeParameters['shop']),
                $headCrumb(
                    [
                        'name'       => 'shops.show.webpages.index',
                        'parameters' =>
                            [
                                $routeParameters['shop']->slug
                            ]
                    ]
                )
            ),
            default => []
        };
    }
}
