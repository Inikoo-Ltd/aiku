<?php

/*
 * author Louis Perez
 * created on 23-12-2025-14h-17m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Actions\Masters\MasterVariant;

use App\Actions\OrgAction;
use App\Models\Masters\MasterAsset;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use App\Models\Masters\MasterVariant;
use Lorisleiva\Actions\ActionRequest;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Str;

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
    public function inMasterDepartmentInMasterShop(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterProductCategory $masterFamily, MasterVariant $masterVariant, ActionRequest $request): MasterVariant
    {
        $this->parent = $masterFamily;
        $group        = group();
        $this->initialisationFromGroup($group, $request);

        return $this->handle($masterVariant);
    }

    /** @noinspection PhpUnusedParameterInspection */
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
        $masterProductInVariant = MasterAsset::whereIn('id', data_get($masterVariant->data, 'products.*.product.id'))->get();

        return Inertia::render(
            'EditModel',
            [
                'title'       => __('Edit Master Variant'),
                'breadcrumbs' => [],
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
                'formData' => [
                    'blueprint' => [
                        [
                            'title'   => __('Variant Detail'),
                            'label'   => __('Variant Detail'),
                            'icon'    => 'fa-light fa-key',
                            'current' => true,
                            'fields'  => [
                                
                            ],
                        ],
                    ],
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.master_variant.update',
                            'parameters' => [$masterVariant->slug]
                        ],
                    ]
                ]
            ]
        );
    }

    /**
     * @throws \Throwable
     */
    public function asController(MasterProductCategory $masterProductCategory, ActionRequest $request): MasterVariant
    {
        $this->initialisationFromGroup($masterProductCategory->group, $request);

        return $this->handle($masterProductCategory, $this->validatedData);
    }
}
