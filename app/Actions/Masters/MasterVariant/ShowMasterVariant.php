<?php

/*
 * author Louis Perez
 * created on 23-12-2025-13h-27m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Actions\Masters\MasterVariant;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersAuthorisation;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use App\Models\Masters\MasterVariant;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;
use Inertia\Response;
use App\Http\Resources\Masters\MasterProductVariantResource;

class ShowMasterVariant extends OrgAction
{
    use WithMastersAuthorisation;

    private MasterProductCategory $parent;

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterDepartment(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterProductCategory $masterFamily, MasterVariant $masterVariant, ActionRequest $request): Response
    {
        $this->parent = $masterFamily;
        $group        = group();
        $this->initialisationFromGroup($group, $request);

        return $this->handle($masterVariant);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterDepartmentInMasterShop(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterProductCategory $masterFamily, MasterVariant $masterVariant, ActionRequest $request): Response
    {
        $this->parent = $masterFamily;
        $group        = group();
        $this->initialisationFromGroup($group, $request);

        return $this->handle($masterVariant);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterSubDepartmentInMasterDepartment(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterProductCategory $masterSubDepartment, MasterProductCategory $masterFamily, MasterVariant $masterVariant, ActionRequest $request): Response
    {
        $this->parent = $masterFamily;
        $group        = group();
        $this->initialisationFromGroup($group, $request);

        return $this->handle($masterVariant);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterSubDepartment(MasterShop $masterShop, MasterProductCategory $masterSubDepartment, MasterProductCategory $masterFamily, MasterVariant $masterVariant, ActionRequest $request): Response
    {
        $this->parent = $masterFamily;
        $group        = group();
        $this->initialisationFromGroup($group, $request);

        return $this->handle($masterVariant);
    }

    public function inMasterFamily(MasterShop $masterShop, MasterProductCategory $masterFamily, MasterVariant $masterVariant, ActionRequest $request): Response
    {
        $this->parent = $masterFamily;
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($masterVariant);
    }

    /**
     * @throws \Throwable
     */
    public function handle(MasterVariant $masterVariant): Response
    {
        $masterVariant->leaderProduct;
        $masterProductInVariant = MasterAsset::query()
            ->whereIn(
                'id',
                data_get($masterVariant->data, 'products.*.product.id', [])
            )
            ->get();

        $products = MasterProductVariantResource::collection($masterProductInVariant);

        return Inertia::render(
            'Masters/Variant',
            [
                'breadcrumbs'     => [],
                'title'           => __('Show Variant'),
                'pageHead'        => [
                    'title' => $masterVariant->code,
                    'actions'       => [
                        [
                            'type'  => 'button',
                            'style' => 'edit',
                            'route' => [
                                'name'       => preg_replace('/show$/', 'edit', request()->route()->getName()),
                                'parameters' => request()->route()->originalParameters()
                            ]
                        ],
                    ],
                ],
                'data'            => $masterVariant,
                'master_products' => $products,
            ]
        );
    }


    /**
     * @throws \Throwable
     */
    public function asController(MasterVariant $masterVariant, ActionRequest $request): Response
    {
        $this->initialisationFromGroup($masterVariant->group, $request);
        return $this->handle($masterVariant);
    }
}
