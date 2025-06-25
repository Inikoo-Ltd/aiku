<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Mon, 13 Mar 2023 15:05:41 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Catalogue\Collection\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditCollection extends OrgAction
{
    use WithCatalogueAuthorisation;

    private Organisation|Shop|ProductCategory $parent;

    public function handle(Collection $collection): Collection
    {
        return $collection;
    }

    public function asController(Organisation $organisation, Shop $shop, Collection $collection, ActionRequest $request): Collection
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($collection);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inDepartment(Organisation $organisation, Shop $shop, ProductCategory $department, Collection $collection, ActionRequest $request): Collection
    {
        $this->parent = $department;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($collection);
    }


    /** @noinspection PhpUnusedParameterInspection */
    public function inSubDepartment(Organisation $organisation, Shop $shop, ProductCategory $department, ProductCategory $subDepartment, Collection $collection, ActionRequest $request): Collection
    {
        $this->parent = $subDepartment;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($collection);
    }

    public function htmlResponse(Collection $collection, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'title'       => __('collections'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $this->parent,
                    $collection,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    'title'   => $collection->code,
                    'model'   => __('Edit collections'),
                    'icon'    => 'fal fa-cube',
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ]
                    ]
                ],
                'formData'    => [
                    'blueprint' => [
                        [
                            'label'  => __('Properties collection'),
                            'fields' => [
                                'name'        => [
                                    'type'  => 'input',
                                    'label' => __('name'),
                                    'value' => $collection->name
                                ],
                                'description' => [
                                    'type'  => 'textarea',
                                    'label' => __('description'),
                                    'value' => $collection->description
                                ],
                                "image"       => [
                                    "type"  => "image_crop_square",
                                    "label" => __("Image"),
                                    "value" => $collection->imageSources(720, 480),
                                ],
                            ]
                        ]

                    ],
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.org.catalogue.collections.update',
                            'parameters' => [
                                'organisation' => $collection->organisation_id,
                                'shop'         => $collection->shop_id,
                                'collection'   => $collection->id
                            ]

                        ],
                    ]
                ]

            ]
        );
    }

    public function getBreadcrumbs(Organisation|Shop|ProductCategory $parent, Collection $collection, string $routeName, array $routeParameters): array
    {
        return ShowCollection::make()->getBreadcrumbs(
            $parent,
            $collection,
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '('.__('Editing').')'
        );
    }
}
