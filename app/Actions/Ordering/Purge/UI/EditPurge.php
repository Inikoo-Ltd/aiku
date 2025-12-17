<?php

/*
 * author Arya Permana - Kirin
 * created on 04-11-2024-15h-47m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Ordering\Purge\UI;

use App\Actions\OrgAction;
use App\Enums\Ordering\Purge\PurgeStateEnum;
use App\Enums\Ordering\Purge\PurgeTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Purge;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\LaravelOptions\Options;

class EditPurge extends OrgAction
{
    public function handle(Purge $purge): Purge
    {
        return $purge;
    }

    public function asController(Organisation $organisation, Shop $shop, Purge $purge, ActionRequest $request): Purge
    {
        $this->initialisationFromShop($shop, $request);
        return $this->handle($purge);
    }


    public function htmlResponse(Purge $purge, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'title'       => __('Purge'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $purge,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'                            => [
                    'previous' => $this->getPrevious($purge, $request),
                    'next'     => $this->getNext($purge, $request),
                ],
                'pageHead' => [
                    'title'    => $purge->scheduled_at,
                    'icon'     => [
                        'title' => __('Purge'),
                        'icon'  => 'fal fa-trash-alt'
                    ],
                    'actions'  => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ]
                ],

                'formData' => [
                    'blueprint' => [
                        [
                            'title'  => __('Edit purge'),
                            'fields' => [
                                'type' => [
                                    'type'  => 'select',
                                    'label' => __('Type'),
                                    'options'  => Options::forEnum(PurgeTypeEnum::class),
                                    'value' => $purge->type
                                ],
                                'state' => [
                                    'type'  => 'select',
                                    'label' => __('state'),
                                    'options'  => Options::forEnum(PurgeStateEnum::class),
                                    'value' => $purge->state
                                ],
                            ],
                        ]
                    ],

                    'args' => [
                        'updateRoute' => [
                            'name'       => 'grp.models.purge.update',
                            'parameters' => $purge->id

                        ],
                    ]
                ]
            ]
        );
    }

    public function getBreadcrumbs(Purge $purge, string $routeName, array $routeParameters): array
    {
        return ShowPurge::make()->getBreadcrumbs(
            purge: $purge,
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '(' . __('Editing') . ')'
        );
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
            'grp.org.shops.show.ordering.purges.edit' => [
                'label' => $purge->scheduled_at,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $purge->organisation->slug,
                        'shop'         => $purge->shop->slug,
                        'purge'       => $purge->id
                    ]
                ]
            ],
        };
    }
}
