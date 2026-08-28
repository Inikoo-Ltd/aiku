<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset\UI;

use App\Actions\Goods\UI\WithMasterCatalogueSubNavigation;
use App\Actions\Masters\MasterProductCategory\WithMasterDepartmentSubNavigation;
use App\Actions\Masters\MasterProductCategory\WithMasterFamilySubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersAuthorisation;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Http\Resources\Masters\MasterProductsResource;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexMasterProductsSales extends OrgAction
{
    use WithMasterCatalogueSubNavigation;
    use WithMasterDepartmentSubNavigation;
    use WithMasterFamilySubNavigation;
    use WithMastersAuthorisation;

    public const string PREFIX = 'sales';

    private MasterShop|MasterProductCategory $parent;

    public function handle(MasterShop|MasterProductCategory $parent): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator $masterAssets */
        $masterAssets = IndexMasterProducts::make()->handle($parent, prefix: self::PREFIX);

        return $masterAssets;
    }

    public function jsonResponse(LengthAwarePaginator $masterAssets): AnonymousResourceCollection
    {
        return MasterProductsResource::collection($masterAssets);
    }

    public function htmlResponse(LengthAwarePaginator $masterAssets, ActionRequest $request): Response
    {
        $parent = $this->parent;

        if ($parent instanceof MasterShop) {
            $subNavigation = $this->getMasterShopNavigation($parent);
            $icon          = [
                'icon'  => ['fal', 'fa-store-alt'],
                'title' => __('Master shop')
            ];
        } elseif ($parent->type == MasterProductCategoryTypeEnum::DEPARTMENT) {
            $subNavigation = $this->getMasterDepartmentSubNavigation($parent);
            $icon          = [
                'icon'  => ['fal', 'fa-folder-tree'],
                'title' => __('Master Department')
            ];
        } else {
            $subNavigation = $this->getMasterFamilySubNavigation($parent);
            $icon          = [
                'icon'  => ['fal', 'fa-folder'],
                'title' => __('Master family')
            ];
        }

        return Inertia::render(
            'Masters/MasterProducts',
            [
                'breadcrumbs'             => $this->getBreadcrumbs(
                    $parent,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'              => [],
                'title'                   => $parent->name,
                'familyId'                => $parent instanceof MasterProductCategory ? $parent->id : null,
                'masterProductCategoryId' => $parent->id,
                'currency'                => $parent->group->currency->code,
                'storeProductRoute'       => [],
                'editable_table'          => false,
                'hide_bulk_edit'          => true,
                'taxPresetOptions'        => [],
                'pageHead'                => [
                    'title'         => $parent->name,
                    'color'         => '#0284c7',
                    'icon'          => $icon,
                    'model'         => '',
                    'afterTitle'    => [
                        'label' => __('Master products sales')
                    ],
                    'iconRight'     => [
                        'icon' => 'fal fa-money-bill-wave',
                    ],
                    'subNavigation' => $subNavigation,
                    'actions'       => [],
                ],
                'tabs'                    => [
                    'current'    => self::PREFIX,
                    'navigation' => [],
                ],
                self::PREFIX              => MasterProductsResource::collection($masterAssets),
            ]
        )->table(
            IndexMasterProducts::make()->tableStructure($parent, prefix: self::PREFIX, sales: true)
        );
    }

    public function getBreadcrumbs(MasterShop|MasterProductCategory $parent, string $routeName, array $routeParameters): array
    {
        return IndexMasterProducts::make()->getBreadcrumbs(
            $parent,
            $routeName,
            $routeParameters,
            '('.__('Sales').')'
        );
    }

    public function inMasterShop(MasterShop $masterShop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterShop;
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($masterShop);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterDepartmentInMasterShop(MasterShop $masterShop, MasterProductCategory $masterDepartment, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterDepartment;
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($masterDepartment);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterFamilyInMasterShop(MasterShop $masterShop, MasterProductCategory $masterFamily, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterFamily;
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($masterFamily);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterFamilyInMasterDepartmentInMasterShop(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterProductCategory $masterFamily, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterFamily;
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($masterFamily);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterFamilyInMasterSubDepartmentInMasterDepartment(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterProductCategory $masterSubDepartment, MasterProductCategory $masterFamily, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterFamily;
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($masterFamily);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterFamilyInMasterSubDepartment(MasterShop $masterShop, MasterProductCategory $masterSubDepartment, MasterProductCategory $masterFamily, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterFamily;
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($masterFamily);
    }
}
