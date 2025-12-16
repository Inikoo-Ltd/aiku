<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 26-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Masters\MasterAsset\UI;

use App\Actions\OrgAction;
use App\Http\Resources\Catalogue\MasterProductCategoryResource;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\Actions\Masters\MasterProductCategory\UI\ShowMasterFamily;

class ShowCreateVariant extends OrgAction
{
    public function asController(MasterShop $masterShop, MasterProductCategory $masterFamily, ActionRequest $request): MasterProductCategory
    {
        $group        = group();
        $this->initialisationFromGroup($group, $request);

        return $this->handle($masterFamily);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterDepartment(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterProductCategory $masterFamily, ActionRequest $request): MasterProductCategory
    {
        $group        = group();
        $this->initialisationFromGroup($group, $request);

        return $this->handle($masterFamily);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterSubDepartment(MasterShop $masterShop, MasterProductCategory $masterSubDepartment, MasterProductCategory $masterFamily, ActionRequest $request): MasterProductCategory
    {
        $group        = group();
        $this->initialisationFromGroup($group, $request);

        return $this->handle($masterFamily);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterSubDepartmentInMasterDepartment(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterProductCategory $masterSubDepartment, MasterProductCategory $masterFamily, ActionRequest $request): MasterProductCategory
    {
        $group        = group();
        $this->initialisationFromGroup($group, $request);

        return $this->handle($masterFamily);
    }

    public function handle(MasterProductCategory $masterFamily): MasterProductCategory
    {
        return $masterFamily;
    }



    public function htmlResponse(MasterProductCategory $masterFamily, ActionRequest $request): Response
    {
        return Inertia::render(
            'Masters/CreateVariant',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $masterFamily,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Create Master Variant'),
                'pageHead'    => [
                    'title'   => __('Create Master Variant'),
                ],
                'master_family' => MasterProductCategoryResource::make($masterFamily),
                'master_assets_route' => [
                    'name' => 'grp.masters.master_shops.show.master_families.master_products.index',
                    'parameters' => [
                        'masterShop'    => $masterFamily->masterShop->slug,
                        'masterFamily'  => $masterFamily->slug
                    ]
                ],
                'save_route' => [
                    'name' => 'grp.models.master_variant.store',
                    'parameters' => [
                        'masterProductCategory'    => $masterFamily->id
                    ]
                ]
            ]
        );
    }


    public function getBreadcrumbs(MasterProductCategory $masterFamily, string $routeName, array $routeParameters): array
    {
        return ShowMasterFamily::make()->getBreadcrumbs(
            masterFamily: $masterFamily,
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '('.__('Creating master variants').')'
        );
    }
}
