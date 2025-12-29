<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 26-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Masters\MasterShop\UI;

use App\Actions\Masters\MasterShop\WithMasterShopNavigation;
use App\Actions\OrgAction;
use App\Models\Masters\MasterShop;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditMasterShop extends OrgAction
{
    use WithMasterShopNavigation;

    public function asController(MasterShop $masterShop, ActionRequest $request): Response
    {
        $group = group();
        $this->initialisationFromGroup($group, $request);

        return $this->handle($masterShop, $request);
    }

    public function handle(MasterShop $masterShop, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $masterShop
                ),
                'navigation'  => [
                    'previous' => $this->getPreviousModel($masterShop, $request),
                    'next'     => $this->getNextModel($masterShop, $request),
                ],
                'title'       => __('Edit master shop').': '.$masterShop->code,
                'pageHead'    => [
                    'title'   => __('Edit master shop'),
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('Exit edit'),
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
                                    'label' => __('Code'),
                                    'value' => $masterShop->code
                                ],
                                'name' => [
                                    'type'  => 'input',
                                    'label' => __('Name'),
                                    'value' => $masterShop->name
                                ]
                            ]
                        ],
                        [
                            'label'  => __('Pricing'),
                            'icon'   => 'fa-light fa-money-bill',
                            'fields' => [
                                'cost_price_ratio' => [
                                    'type'        => 'input_number',
                                    'bind'        => [
                                        'maxFractionDigits' => 3
                                    ],
                                    'label'       => __('Pricing ratio'),
                                    'placeholder' => __('Cost price ratio'),
                                    'required'    => true,
                                    'value'       => $masterShop->cost_price_ratio,
                                    'min'         => 0
                                ],
                                'price_rrp_ratio'  => [
                                    'type'        => 'input_number',
                                    'bind'        => [
                                        'maxFractionDigits' => 3
                                    ],
                                    'label'       => __('RRP ratio'),
                                    'placeholder' => __('Price rrp ratio'),
                                    'required'    => true,
                                    'value'       => $masterShop->price_rrp_ratio,
                                    'min'         => 0
                                ]
                            ]
                        ]
                    ],
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.master_shops.update',
                            'parameters' => [
                                'masterShop' => $masterShop->id
                            ]
                        ],
                    ],
                ]
            ]
        );
    }

    public function getBreadcrumbs(MasterShop $masterShop): array
    {
        return ShowMasterShop::make()->getBreadcrumbs(
            $masterShop,
            suffix: '('.__('Editing').')'
        );
    }

}
