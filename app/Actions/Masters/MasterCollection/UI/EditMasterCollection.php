<?php

/*
 * Author: Eka Yudinata <ekayudinath@gmail.com>
 * Created: 11/10/2025
 * Copyright (c) 2025, Eka Yudinata
 */

namespace App\Actions\Masters\MasterCollection\UI;

use App\Actions\OrgAction;
use App\Models\Masters\MasterCollection;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Support\Facades\Log;

class EditMasterCollection extends OrgAction
{
    public function asController(MasterShop $masterShop,  MasterCollection $masterCollection, ActionRequest $request): Response
    {
        $this->initialisationFromGroup(group(), $request);
        return $this->handle($masterShop, $masterCollection, $request);
    }

    public function handle(MasterShop $parent, MasterCollection $masterCollection, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $parent,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Edit Master Collection'),
                'pageHead'    => [
                    'title'   => __('Edit master collection'),
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('Cancel'),
                            'route' => [
                                'name' => 'grp.masters.master_shops.show.master_collections.show',
                                'parameters' => array_values($request->route()->originalParameters())
                            ],
                        ]
                    ]
                ],
                'formData'    => [
                    'blueprint' =>
                        [
                            [
                                'title'  => __('Master Collection'),
                                'fields' => [
                                    'code' => [
                                        'type'     => 'input',
                                        'label'    => __('code'),
                                        'value'    => $masterCollection->code,
                                        'required' => true
                                    ],
                                    'name' => [
                                        'type'     => 'input',
                                        'label'    => __('name'),
                                        'value'    => $masterCollection->name,
                                        'required' => true
                                    ],
                                    'description' => [
                                        'type'     => 'textarea',
                                        'label'    => __('description'),
                                        'value'    => $masterCollection->description,
                                        'required' => false
                                    ],
                                ]
                            ]
                        ],
                        'args' => [
                             'updateRoute' => [
                                'name'       => 'grp.models.master_collection.update',
                                'parameters' => [
                                    'masterCollection' => $masterCollection->id,
                                ]
                            ]

                        ],
                ]
            ]
        );
    }

    public function getBreadcrumbs(MasterProductCategory|MasterShop|MasterCollection $parent, string $routeName, array $routeParameters): array
    {
        return array_merge(
                IndexMasterCollections::make()->getBreadcrumbs(
                    parent: $parent,
                    routeName: preg_replace('/edit$/', 'index', $routeName),
                    routeParameters: $routeParameters
                ),
                [
                    [
                        'type'          => 'editingModel',
                        'editingModel' => [
                            'label' => __('Editing collection'),
                        ]
                    ]
                ]
            );
    }
}
