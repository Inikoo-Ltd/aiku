<?php

/*
 * author Louis Perez
 * created on 23-12-2025-13h-27m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Actions\Masters\MasterVariant;

use App\Actions\GrpAction;
use App\Actions\Masters\MasterProductCategory\UI\ShowMasterFamily;
use App\Actions\Traits\Authorisations\WithMastersAuthorisation;
use App\Enums\UI\SupplyChain\MasterVariantTabsEnum;
use App\Http\Resources\Masters\MasterProductVariantResource;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use App\Models\Masters\MasterVariant;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowMasterVariant extends GrpAction
{
    use WithMastersAuthorisation;

    private MasterProductCategory $parent;

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterDepartment(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterProductCategory $masterFamily, MasterVariant $masterVariant, ActionRequest $request): Response
    {
        $this->parent = $masterFamily;
        $group        = group();
        $this->initialisation($group, $request)->withTab(MasterVariantTabsEnum::values());

        return $this->handle($masterVariant);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterDepartmentInMasterShop(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterProductCategory $masterFamily, MasterVariant $masterVariant, ActionRequest $request): Response
    {
        $this->parent = $masterFamily;
        $group        = group();
        $this->initialisation($group, $request)->withTab(MasterVariantTabsEnum::values());

        return $this->handle($masterVariant);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterSubDepartmentInMasterDepartment(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterProductCategory $masterSubDepartment, MasterProductCategory $masterFamily, MasterVariant $masterVariant, ActionRequest $request): Response
    {
        $this->parent = $masterFamily;
        $group        = group();
        $this->initialisation($group, $request)->withTab(MasterVariantTabsEnum::values());

        return $this->handle($masterVariant);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterSubDepartment(MasterShop $masterShop, MasterProductCategory $masterSubDepartment, MasterProductCategory $masterFamily, MasterVariant $masterVariant, ActionRequest $request): Response
    {
        $this->parent = $masterFamily;
        $group        = group();
        $this->initialisation($group, $request)->withTab(MasterVariantTabsEnum::values());

        return $this->handle($masterVariant);
    }

    public function inMasterFamily(MasterShop $masterShop, MasterProductCategory $masterFamily, MasterVariant $masterVariant, ActionRequest $request): Response
    {
        $this->parent = $masterFamily;
        $this->initialisation(group(), $request)->withTab(MasterVariantTabsEnum::values())->withTab(MasterVariantTabsEnum::values());

        return $this->handle($masterVariant);
    }

    /**
     * @throws \Throwable
     */
    public function handle(MasterVariant $masterVariant): Response
    {
        $masterProductInVariant = MasterProductVariantResource::collection(MasterAsset::whereIn('id', data_get($masterVariant->data, 'products.*.product.id', []))->get());

        return Inertia::render(
            'Masters/MasterVariant',
            [
                'breadcrumbs'     => $this->getBreadcrumbs(
                    $masterVariant,
                    request()->route()->getName(),
                    request()->route()->originalParameters()
                ),
                'title'           => __('Show Variant'),
                'pageHead'        => [
                    'title' => $masterVariant->code,
                    'model'         => __('Master Variants'),
                    'icon'          => [
                        'icon'  => ['fal', 'fa-shapes'],
                        'title' => __('Department')
                    ],
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
                'tabs'                    => [
                    'current'    => $this->tab,
                    'navigation' => MasterVariantTabsEnum::navigation()
                ],
                MasterVariantTabsEnum::SHOWCASE->value => 
                    $this->tab === MasterVariantTabsEnum::SHOWCASE->value ? [
                        'master_variant'            => $masterVariant,
                        'master_products' => $masterProductInVariant
                    ] : Inertia::lazy(fn () => [
                        'master_variant'            => $masterVariant,
                        'master_products' => $masterProductInVariant,
                    ]),
            ]
        );
    }


    /**
     * @throws \Throwable
     */
    public function asController(MasterVariant $masterVariant, ActionRequest $request): Response
    {
        $this->initialisation($masterVariant->group, $request);
        return $this->handle($masterVariant);
    }

    public function getBreadcrumbs(MasterVariant $masterVariant, string $routeName, array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (MasterVariant $masterVariant, array $routeParameters, $suffix) {
            return [

                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'label' => __('Master variant')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $masterVariant->code,
                        ],
                    ],
                    'suffix'         => $suffix,

                ],

            ];
        };

        return array_merge(
            ShowMasterFamily::make()->getBreadcrumbs(
                masterFamily: $masterVariant->masterFamily,
                routeName: 'grp.masters.master_shops.show.master_families.show',
                routeParameters: $routeParameters,
            ),
            $headCrumb(
                $masterVariant,
                [
                    'model' => [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ]
                ],
                $suffix
            )
        );
    }
}
