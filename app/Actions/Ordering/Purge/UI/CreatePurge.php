<?php

/*
 * author Arya Permana - Kirin
 * created on 04-11-2024-14h-37m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Ordering\Purge\UI;

use App\Actions\OrgAction;
use App\Enums\Ordering\Purge\PurgeTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\LaravelOptions\Options;

class CreatePurge extends OrgAction
{
    public function handle(Shop $shop, ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'    => __('New purge'),
                'pageHead' => [
                    'title'        => __('New purge'),
                    'actions'      => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('Cancel'),
                            'route' => [
                                'name'       => 'grp.org.shops.show.ordering.purges.index',
                                'parameters' => array_values($request->route()->originalParameters())
                            ],
                        ]
                    ]
                ],
                'formData' => [
                    'fullLayout' => true,
                    'blueprint'  =>
                        [
                            [
                                'title'  => __('New Purge'),
                                'fields' => [
                                    'type' => [
                                        'type'     => 'select',
                                        'label'    => __('type'),
                                        'required' => true,
                                        'options'  => Options::forEnum(PurgeTypeEnum::class),
                                    ],
                                    'scheduled_at' => [
                                        'type'     => 'date',
                                        'label'    => __('schedule'),
                                        'required' => true,
                                    ],
                                ]
                            ]
                        ],
                    'route' => [
                        'name'       => 'grp.models.purge.store',
                        'parameters' => [
                            'shop'         => $shop->id,
                        ]
                    ]
                ],

            ]
        );
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $request);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            IndexPurges::make()->getBreadcrumbs(
                routeName: preg_replace('/create$/', 'index', $routeName),
                routeParameters: $routeParameters,
            ),
            [
                [
                    'type'         => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating purge'),
                    ]
                ]
            ]
        );
    }
}
