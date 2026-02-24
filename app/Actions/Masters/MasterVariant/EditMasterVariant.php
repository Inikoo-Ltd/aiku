<?php

/*
 * author Louis Perez
 * created on 23-12-2025-14h-17m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Actions\Masters\MasterVariant;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use App\Models\Masters\MasterVariant;
use Lorisleiva\Actions\ActionRequest;
use Inertia\Inertia;
use Inertia\Response;

class EditMasterVariant extends OrgAction
{
    use WithMastersEditAuthorisation;

    private MasterProductCategory $parent;

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterDepartment(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterProductCategory $masterFamily, MasterVariant $masterVariant, ActionRequest $request): MasterVariant
    {
        $this->parent = $masterFamily;
        $group        = group();
        $this->initialisationFromGroup($group, $request);

        return $this->handle($masterVariant);
    }

    /** @noinspection PhpUnusedParameterInspection */
    /**
     * @throws \Throwable
     */
    public function inMasterDepartmentInMasterShop(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterProductCategory $masterFamily, MasterVariant $masterVariant, ActionRequest $request): MasterVariant
    {
        $this->parent = $masterFamily;
        $group        = group();
        $this->initialisationFromGroup($group, $request);

        return $this->handle($masterVariant);
    }

    /** @noinspection PhpUnusedParameterInspection */
    /**
     * @throws \Throwable
     */
    public function inMasterSubDepartmentInMasterDepartment(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterProductCategory $masterSubDepartment, MasterProductCategory $masterFamily, MasterVariant $masterVariant, ActionRequest $request): MasterVariant
    {
        $this->parent = $masterFamily;
        $group        = group();
        $this->initialisationFromGroup($group, $request);

        return $this->handle($masterVariant);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterSubDepartment(MasterShop $masterShop, MasterProductCategory $masterSubDepartment, MasterProductCategory $masterFamily, MasterVariant $masterVariant, ActionRequest $request): MasterVariant
    {
        $this->parent = $masterFamily;
        $group        = group();
        $this->initialisationFromGroup($group, $request);

        return $this->handle($masterVariant);
    }

    public function inMasterFamily(MasterShop $masterShop, MasterProductCategory $masterFamily, MasterVariant $masterVariant, ActionRequest $request): MasterVariant
    {
        $this->parent = $masterFamily;
        $group        = group();
        $this->initialisationFromGroup($group, $request);

        return $this->handle($masterVariant);
    }

    /**
     * @throws \Throwable
     */
    public function handle(MasterVariant $masterVariant)
    {
        return $masterVariant;
    }

    public function htmlResponse(MasterVariant $masterVariant, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'title'       => __('Edit Master Variant'),
                'breadcrumbs' => ShowMasterVariant::make()->getBreadcrumbs(
                    $masterVariant,
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
                            'label'   => __('Variant'),
                            'icon'    => 'fa-light fa-shapes',
                            'fields'  => [
                                'variant'  => [
                                    'type'     => 'input-variant',
                                    'label'    => __('Variants'),
                                    'value'    => $masterVariant->data,
                                    'required' => true,
                                    'full'     => true,
                                    'noSaveButton' => true,
                                    'save_route' => [
                                        'name'       => 'grp.models.master_variant.update',
                                        'parameters' => [$masterVariant->id]
                                    ],
                                    'master_assets_route' => [
                                        'name' => 'grp.masters.master_shops.show.master_families.master_products.index.filter_in_variant',
                                        'parameters' => [
                                            'masterShop'    => $masterVariant->masterShop->slug,
                                            'masterFamily'  => $masterVariant->masterFamily->slug,
                                            'filterInVariant'   => $masterVariant->id
                                        ]
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.master_variant.update',
                            'parameters' => [$masterVariant->id]
                        ],
                    ]
                ]
            ]
        );
    }
}
