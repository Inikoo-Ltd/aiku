<?php

/*
 * author Arya Permana - Kirin
 * created on 30-01-2025-16h-35m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Spaces\UI;

use App\Actions\RetinaAction;
use App\Enums\UI\Fulfilment\SpaceTabsEnum;
use App\Http\Resources\Fulfilment\SpaceResource;
use App\Models\Fulfilment\Space;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowRetinaSpace extends RetinaAction
{
    public function handle(Space $space): Space
    {
        return $space;
    }

    public function asController(Space $space, ActionRequest $request): Space
    {

        $this->initialisation($request)->withTab(SpaceTabsEnum::values());

        return $this->handle($space);
    }

    public function htmlResponse(Space $space, ActionRequest $request): Response
    {
        return Inertia::render(
            'Space/RetinaSpace',
            [
                'title'       => __('Space'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'                            => [
                    'previous' => $this->getPrevious($space, $request),
                    'next'     => $this->getNext($space, $request),
                ],
                'pageHead'    => [
                    'title'     => $space->reference,
                    'model'     => __('Space'),
                    'icon'      =>
                        [
                            'icon'  => ['fal', 'fa-parking'],
                            'title' => __('Space')
                        ],
                ],
                'tabs' => [
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
            'retina.fulfilment.spaces.show' =>
            array_merge(
                IndexRetinaSpaces::make()->getBreadcrumbs(
                    $routeName
                ),
                $headCrumb(
                    $space,
                    [
                        'index' => [
                            'name'       => 'retina.fulfilment.spaces.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'retina.fulfilment.spaces.show',
                            'parameters' => [
                                'space' => $space->slug
                            ]
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
        $previous = Space::where('slug', '<', $space->slug)->orderBy('id', 'desc')->first();
        return $this->getNavigation($previous, $request->route()->getName());

    }

    public function getNext(Space $space, ActionRequest $request): ?array
    {
        $next = Space::where('slug', '>', $space->slug)->orderBy('id')->first();
        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?Space $space, string $routeName): ?array
    {
        if (!$space) {
            return null;
        }

        return match ($routeName) {
            'retina.fulfilment.spaces.show' => [
                'label' => $space->reference,
                'route' => [
                    'name'      => $routeName,
                    'parameters' => [
                        'space'              => $space->slug
                    ]

                ]
            ],
        };
    }
}
