<?php

/*
 * author Arya Permana - Kirin
 * created on 30-01-2025-16h-35m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Fulfilment\Space\UI;

use App\Actions\Fulfilment\FulfilmentCustomer\ShowFulfilmentCustomer;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithFulfilmentShopAuthorisation;
use App\Enums\UI\Fulfilment\SpaceTabsEnum;
use App\Http\Resources\Fulfilment\SpaceResource;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\Space;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowSpace extends OrgAction
{
    use WithFulfilmentShopAuthorisation;

    public function handle(Space $space): Space
    {
        return $space;
    }

    public function asController(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, Space $space, ActionRequest $request): Space
    {
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(SpaceTabsEnum::values());

        return $this->handle($space);
    }

    public function htmlResponse(Space $space, ActionRequest $request): Response
    {
        $actions = null;
        if ($request->user()->authTo("supervisor-fulfilment-shop.".$this->fulfilment->id)) {
            $actions = [
                [
                    'type'  => 'button',
                    'style' => 'edit',
                    'route' => [
                        'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                        'parameters' => array_values($request->route()->originalParameters())
                    ]
                ]
            ];
        }

        return Inertia::render(
            'Org/Fulfilment/Space',
            [
                'title'       => __('Space'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($space, $request),
                    'next'     => $this->getNext($space, $request),
                ],
                'pageHead'    => [
                    'title'   => $space->reference,
                    'model'   => __('Space'),
                    'icon'    =>
                        [
                            'icon'  => ['fal', 'fa-parking'],
                            'title' => __('Space')
                        ],
                    'actions' => $actions
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => SpaceTabsEnum::navigation()
                ],
                'showcase'    => SpaceResource::make($space),

            ]
        );
    }

    public function jsonResponse(Space $space): SpaceResource
    {
        return new SpaceResource($space);
    }


    public function getBreadcrumbs(string $routeName, array $routeParameters, $suffix = ''): array
    {
        $headCrumb = function (Space $space, array $routeParameters, string $suffix) {
            return [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Spaces')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $space->slug,
                        ],

                    ],
                    'suffix'         => $suffix
                ],
            ];
        };

        $space = Space::where('slug', $routeParameters['space'])->first();


        return match ($routeName) {
            'grp.org.fulfilments.show.crm.customers.show.spaces.show',
            'grp.org.fulfilments.show.crm.customers.show.spaces.edit' =>
            array_merge(
                ShowFulfilmentCustomer::make()->getBreadcrumbs(
                    Arr::only($routeParameters, ['organisation', 'fulfilment', 'fulfilmentCustomer'])
                ),
                $headCrumb(
                    $space,
                    [
                        'index' => [
                            'name'       => 'grp.org.fulfilments.show.crm.customers.show.spaces.index',
                            'parameters' => Arr::only(
                                $routeParameters,
                                ['organisation', 'fulfilment', 'fulfilmentCustomer']
                            )
                        ],
                        'model' => [
                            'name'       => 'grp.org.fulfilments.show.crm.customers.show.spaces.show',
                            'parameters' => Arr::only(
                                $routeParameters,
                                ['organisation', 'fulfilment', 'fulfilmentCustomer', 'space']
                            )
                        ]
                    ],
                    $suffix
                )
            ),
            default => []
        };
    }

    public function getPrevious(Space $space, ActionRequest $request): ?array
    {
        $previous = Space::where('fulfilment_id', $this->fulfilment->id)->where('slug', '<', $space->slug)->orderBy('id', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(Space $space, ActionRequest $request): ?array
    {
        $next = Space::where('fulfilment_id', $this->fulfilment->id)->where('slug', '>', $space->slug)->orderBy('id')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?Space $space, string $routeName): ?array
    {
        if (!$space) {
            return null;
        }

        return match ($routeName) {
            'grp.org.fulfilments.show.crm.customers.show.spaces.show' => [
                'label' => $space->reference,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation'       => $space->organisation->slug,
                        'fulfilment'         => $space->fulfilment->slug,
                        'fulfilmentCustomer' => $space->fulfilmentCustomer->slug,
                        'space'              => $space->slug
                    ]

                ]
            ],
        };
    }
}
