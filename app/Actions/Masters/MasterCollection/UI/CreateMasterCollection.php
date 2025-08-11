<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Jan 2025 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterCollection\UI;

use App\Actions\OrgAction;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateMasterCollection extends OrgAction
{
    public function asController(MasterShop $masterShop, ActionRequest $request): Response
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($masterShop, $request);
    }

    public function inMasterProductCategory(MasterProductCategory $masterProductCategory, ActionRequest $request): Response
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($masterProductCategory, $request);
    }

    public function handle(MasterProductCategory|MasterShop $parent, ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $parent,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('New Master Collection'),
                'pageHead'    => [
                    'title'   => __('new master collection'),
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('cancel'),
                            'route' => [
                                'name'       => preg_replace('/create$/', 'index', $request->route()->getName()),
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
                                        'required' => true
                                    ],
                                    'name' => [
                                        'type'     => 'input',
                                        'label'    => __('name'),
                                        'required' => true
                                    ],
                                    'description' => [
                                        'type'     => 'textarea',
                                        'label'    => __('description'),
                                        'required' => false
                                    ],
                                    "image" => [
                                        "type"     => "image_crop_square",
                                        "label"    => __("Image"),
                                        "required" => false,
                                    ],
                                ]
                            ]
                        ],
                    'route' => match ($parent::class) {
                        MasterShop::class => [
                            'name' => 'grp.models.master_shops.master_collection.store',
                            'parameters' => [
                                'masterShop' => $parent->id
                            ]
                        ],
                        MasterProductCategory::class => [
                            'name' => 'grp.models.master_product_category.master_collection.store',
                            'parameters' => [
                                'masterProductCategory' => $parent->id
                            ]
                        ],
                        default => null
                    }
                ]
            ]
        );
    }

    public function getBreadcrumbs(MasterProductCategory|MasterShop $parent, string $routeName, array $routeParameters): array
    {
        return array_merge(
            IndexMasterCollections::make()->getBreadcrumbs(
                routeName: preg_replace('/create$/', 'index', $routeName),
            ),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating collection'),
                    ]
                ]
            ]
        );
    }
}