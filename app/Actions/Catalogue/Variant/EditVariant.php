<?php

/*
 * Author Louis Perez
 * Created on 09-07-2026-11h-53m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Catalogue\Variant;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\UI\Catalogue\VariantTabsEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Catalogue\Variant;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use Inertia\Inertia;
use Inertia\Response;

class EditVariant extends OrgAction
{
    use WithCatalogueAuthorisation;

    private Organisation|ProductCategory|Shop $parent;

    /** @noinspection PhpUnusedParameterInspection */
    public function inShop(Organisation $organisation, Shop $shop, ProductCategory $family, Variant $variant, ActionRequest $request): Variant
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request)->withTab(VariantTabsEnum::values());

        return $this->handle($variant);
    }


    /** @noinspection PhpUnusedParameterInspection */
    public function inSubDepartment(Organisation $organisation, Shop $shop, ProductCategory $department, ProductCategory $subDepartment, ProductCategory $family, Variant $variant, ActionRequest $request): Variant
    {
        $this->parent = $subDepartment;

        $this->initialisationFromShop($shop, $request)->withTab(VariantTabsEnum::values());

        return $this->handle($variant);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inSubDepartmentInShop(Organisation $organisation, Shop $shop, ProductCategory $subDepartment, ProductCategory $family, Variant $variant, ActionRequest $request): Variant
    {
        $this->parent = $subDepartment;

        $this->initialisationFromShop($shop, $request)->withTab(VariantTabsEnum::values());

        return $this->handle($variant);
    }

    /**
     * @throws \Throwable
     */
    public function handle(Variant $variant): Variant
    {
        return $variant;
    }

    public function htmlResponse(Variant $variant, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'title'       => __('Edit Master Variant'),
                'breadcrumbs' => ShowVariant::make()->getBreadcrumbs(
                    $variant,
                    preg_replace('/edit$/', 'show', $request->route()->getName()),
                    $request->route()->originalParameters(),
                    '(editing)'
                ),
                'pageHead'    => [
                    'title'     => __('Edit master variant'),
                    'actions'   => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ],
                ],
                'warning'     => [
                    'type'  => 'warning',
                    'title' => 'Warning',
                    'text'  => __('Adding a product into variants would force it as for sale'),
                    'icon'  => ['fas', 'fa-exclamation-triangle'],
                ],
                'formData' => [
                    'blueprint' => [
                        [
                            'label'   => __('Properties'),
                            'icon'    => 'fa-light fa-fingerprint',
                            'fields'  => [
                                'status'    => [
                                    'type'  => 'toggle',
                                    'label' => __('Enable Variant under this shop'),
                                    'value' => $variant->status,

                                ]
                            ],
                        ],
                    ],
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.variant.update',
                            'parameters' => [$variant->id]
                        ],
                    ]
                ]
            ]
        );
    }
}
