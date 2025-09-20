<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 26-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Masters\MasterShop\UI;

use App\Actions\Masters\UI\ShowMastersDashboard;
use App\Actions\OrgAction;
use App\Models\Masters\MasterShop;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditMasterShop extends OrgAction
{
    public function asController(MasterShop $masterShop, ActionRequest $request): Response
    {
        $group        = group();
        $this->initialisationFromGroup($group, $request);

        return $this->handle($masterShop, $request);
    }

    public function handle(MasterShop $masterShop, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                 'breadcrumbs' => $this->getBreadcrumbs(
                     $masterShop,
                     $request->route()->getName(),
                     $request->route()->originalParameters()
                 ),
                'title'       => __('Edit Master Shop'),
                'pageHead'    => [
                    'title'   => __('edit master shop'),
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('cancel'),
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ]
                ],
                'formData'    => [
                    'blueprint' => [
                        [
                            'label'  => __('Id'),
                            'icon'   => 'fa-light fa-fingerprint',
                            'fields' => [
                                'code' => [
                                    'type'  => 'input',
                                    'label' => __('code'),
                                    'value' => $masterShop->code
                                ],
                                'name' => [
                                    'type'  => 'input',
                                    'label' => __('name'),
                                    'value' => $masterShop->name
                                ]
                            ]
                        ],
                        [
                            'label'  => __('Pricing'),
                            'icon'   => 'fa-light fa-money-bill',
                            'fields' => [
                                'cost_price_ratio' => [
                                    'type'          => 'input_number',
                                    'bind' => [
                                        'maxFractionDigits' => 3
                                    ],
                                    'label'         => __('pricing ratio'),
                                    'placeholder'   => __('Cost price ratio'),
                                    'required'      => true,
                                    'value'         => $masterShop->cost_price_ratio,
                                    'min'           => 0
                                ],
                                'price_rrp_ratio' => [
                                    'type'          => 'input_number',
                                    'bind' => [
                                        'maxFractionDigits' => 3
                                    ],
                                    'label'         => __('rrp ratio'),
                                    'placeholder'   => __('price rrp ratio'),
                                    'required'      => true,
                                    'value'         => $masterShop->price_rrp_ratio,
                                    'min'           => 0
                                ]
                            ]
                        ]
                    ],
                    'args'      => [
                        'updateRoute' => [
                            'name' => 'grp.models.master_shops.update',
                            'parameters' => [
                                'masterShop' => $masterShop->id
                            ]
                        ],
                    ],
                ]
            ]
        );
    }

    public function getBreadcrumbs(MasterShop $masterShop, $routeName, $suffix = null): array
    {
        return
            array_merge(
                ShowMastersDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type'           => 'modelWithIndex',
                        'modelWithIndex' => [
                            'index' => [
                                'route' => [
                                    'name'       => 'grp.masters.master_shops.index',
                                    'parameters' => []
                                ],
                                'label' => __('Master shops'),
                                'icon'  => 'fal fa-bars'
                            ],
                            'model' => [
                                'route' => [
                                    'name'       => 'grp.masters.master_shops.show',
                                    'parameters' => [
                                        $masterShop->slug
                                    ]
                                ],
                                'label' => $masterShop->code,
                                'icon'  => 'fal fa-store-alt'
                            ]


                        ],
                        'suffix'         => $suffix,
                    ]
                ]
            );
    }

    public function getPrevious(MasterShop $masterShop, ActionRequest $request): ?array
    {
        $previous = MasterShop::where('code', '<', $masterShop->code)->where('group_id', $this->group->id)->orderBy('code', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(MasterShop $masterShop, ActionRequest $request): ?array
    {
        $next = MasterShop::where('code', '>', $masterShop->code)->where('group_id', $this->group->id)->orderBy('code')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?MasterShop $masterShop, string $routeName): ?array
    {
        if (!$masterShop) {
            return null;
        }

        return match ($routeName) {
            'grp.masters.master_shops.edit' => [
                'label' => $masterShop->name,
                'route' => [
                    'name'       => 'grp.masters.master_shops.edit',
                    'parameters' => [
                        'masterShop' => $masterShop->slug
                    ]

                ]
            ]
        };
    }

}
