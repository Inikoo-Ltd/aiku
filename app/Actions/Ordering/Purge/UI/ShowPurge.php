<?php

/*
 * author Arya Permana - Kirin
 * created on 04-11-2024-14h-57m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Ordering\Purge\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\Ordering\PurgedOrder\UI\IndexPurgedOrders;
use App\Actions\OrgAction;
use App\Enums\UI\Ordering\PurgeTabsEnum;
use App\Http\Resources\Ordering\PurgedOrdersResource;
use App\Http\Resources\Ordering\PurgeResource;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Purge;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowPurge extends OrgAction
{
    public function handle(Purge $purge): Purge
    {
        return $purge;
    }

    public function asController(Organisation $organisation, Shop $shop, Purge $purge, ActionRequest $request): Purge
    {
        $this->initialisationFromShop($shop, $request)->withTab(PurgeTabsEnum::values());
        return $this->handle($purge);
    }

    public function htmlResponse(Purge $purge, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Ordering/Purge',
            [
                'title'       => __('purge'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $purge,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'                            => [
                    'previous' => $this->getPrevious($purge, $request),
                    'next'     => $this->getNext($purge, $request),
                ],
                'pageHead'    => [
                    'title'     => $purge->scheduled_at,
                    'model'     => __('Purge'),
                    'icon'      =>
                        [
                            'icon'  => ['fal', 'fa-trash-alt'],
                            'title' => __('purge')
                        ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'edit',
                            'route' => [
                                'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ],
                    // 'subNavigation' => $this->getCollectionSubNavigation($collection),
                ],
                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => PurgeTabsEnum::navigation()
                ],

                PurgeTabsEnum::SHOWCASE->value => $this->tab == PurgeTabsEnum::SHOWCASE->value ?
                    fn () => GetPurgeShowcase::run($purge)
                    : Inertia::lazy(fn () => GetPurgeShowcase::run($purge)),

                PurgeTabsEnum::PURGED_ORDERS->value => $this->tab == PurgeTabsEnum::PURGED_ORDERS->value ?
                    fn () => PurgedOrdersResource::collection(IndexPurgedOrders::run($purge))
                    : Inertia::lazy(fn () => PurgedOrdersResource::collection(IndexPurgedOrders::run($purge))),


            ]
        )->table(IndexPurgedOrders::make()->tableStructure(prefix:PurgeTabsEnum::PURGED_ORDERS->value));
    }

    public function jsonResponse(Purge $purge): PurgeResource
    {
        return new PurgeResource($purge);
    }

    public function getBreadcrumbs(Purge $purge, string $routeName, array $routeParameters, string $suffix = ''): array
    {
        $headCrumb = function (Purge $purge, array $routeParameters, string $suffix) {
            return [
                [

                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Purges')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $purge->scheduled_at,
                        ],

                    ],
                    'suffix'         => $suffix

                ],
            ];
        };

        return match ($routeName) {
            'grp.org.shops.show.ordering.purges.show',
            => array_merge(
                (new ShowShop())->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $purge,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.ordering.purges.index',
                            'parameters' => Arr::except($routeParameters, ['purge'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.ordering.purges.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            default => []
        };
    }

    public function getPrevious(Purge $purge, ActionRequest $request): ?array
    {
        $previous = Purge::where('id', '<', $purge->id)->orderBy('id', 'desc')->first();
        return $this->getNavigation($previous, $request->route()->getName());

    }

    public function getNext(Purge $purge, ActionRequest $request): ?array
    {
        $next = Purge::where('id', '>', $purge->id)->orderBy('id')->first();
        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?Purge $purge, string $routeName): ?array
    {
        if (!$purge) {
            return null;
        }

        return match ($routeName) {
            'grp.org.shops.show.ordering.purges.show' => [
                'label' => $purge->scheduled_at,
                'route' => [
                    'name'      => $routeName,
                    'parameters' => [
                        'organisation' => $purge->organisation->slug,
                        'shop'         => $purge->shop->slug,
                        'purge'        => $purge->id
                    ]

                ]
            ],
        };
    }
}
