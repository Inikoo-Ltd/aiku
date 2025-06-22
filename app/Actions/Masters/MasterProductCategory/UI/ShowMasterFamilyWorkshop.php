<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 14-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Actions\Catalogue\WithFamilySubNavigation;
use App\Actions\GrpAction;
use App\Actions\Traits\Authorisations\WithMastersAuthorisation;
use App\Enums\UI\SupplyChain\MasterFamilyTabsEnum;
use App\Enums\Web\WebBlockType\WebBlockCategoryScopeEnum;
use App\Http\Resources\Catalogue\DepartmentsResource;
use App\Http\Resources\Catalogue\FamilyResource;
use App\Http\Resources\Masters\MasterProductsResource;
use App\Http\Resources\Web\WebBlockTypesResource;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use App\Models\Web\WebBlockType;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowMasterFamilyWorkshop extends GrpAction
{
    use WithFamilySubNavigation;
    use WithMastersAuthorisation;

    private MasterShop $parent;

    public function handle(MasterProductCategory $masterFamily): MasterProductCategory
    {
        return $masterFamily;
    }


    public function asController(MasterShop $masterShop, MasterProductCategory $masterFamily, ActionRequest $request): MasterProductCategory
    {
        $this->parent = $masterShop;
        $group        = group();

        $this->initialisation($group, $request)->withTab(MasterFamilyTabsEnum::values());

        return $this->handle($masterFamily);
    }


    public function htmlResponse(MasterProductCategory $masterFamily, ActionRequest $request): Response
    {
        return Inertia::render(
            'Goods/FamilyMasterBlueprint',
            [
                'title'       => __('family'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $masterFamily,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($masterFamily, $request),
                    'next'     => $this->getNext($masterFamily, $request),
                ],
                'pageHead'    => [
                    'title'   => $masterFamily->name,
                    'model'   => '',
                    'icon'    => [
                        'icon'  => ['fal', 'fa-folder'],
                        'title' => __('department')
                    ],
                    'actions' => [
                        $this->canEdit ? [
                            'type'  => 'button',
                            'style' => 'edit',
                            'route' => [
                                'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ] : false,
                        $this->canDelete ? [
                            'type'  => 'button',
                            'style' => 'delete',
                            'route' => [
                                'name'       => 'shops.show.families.remove',
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ] : false
                    ],

                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => MasterFamilyTabsEnum::navigation()
                ],

                'upload_image_route' => [
                    'method'     => 'post',
                    'name'       => 'grp.models.master_product_image.upload',
                    'parameters' => [
                        'masterProductCategory' => $masterFamily->id
                    ]
                ],

                'update_route' => [
                    'method'     => 'patch',
                    'name'       => 'grp.models.master_product.update',
                    'parameters' => [
                        'masterProductCategory' => $masterFamily->id
                    ]
                ],

                'family'                   => FamilyResource::make($masterFamily),
                'web_block_types'          => WebBlockTypesResource::collection(WebBlockType::where('category', WebBlockCategoryScopeEnum::FAMILY->value)->get()),
                'assets'                   => MasterProductsResource::collection($masterFamily->masterAssets()->get()),
                'web_block_types_products' => WebBlockTypesResource::collection(WebBlockType::where('category', WebBlockCategoryScopeEnum::PRODUCT->value)->get()),
            ]
        );
    }


    public function jsonResponse(MasterProductCategory $masterFamily): DepartmentsResource
    {
        return new DepartmentsResource($masterFamily);
    }

    public function getBreadcrumbs(MasterProductCategory $masterFamily, string $routeName, array $routeParameters): array
    {
        return ShowMasterFamily::make()->getBreadcrumbs($masterFamily, $routeName, $routeParameters, __('Workshop'));
    }

    public function getPrevious(MasterProductCategory $masterFamily, ActionRequest $request): ?array
    {
        $previous = MasterProductCategory::where('code', '<', $masterFamily->code)->orderBy('code', 'desc')->where('master_shop_id', $this->parent->id)->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(MasterProductCategory $masterFamily, ActionRequest $request): ?array
    {
        $next = MasterProductCategory::where('code', '>', $masterFamily->code)->orderBy('code')->where('master_shop_id', $this->parent->id)->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?MasterProductCategory $masterFamily, string $routeName): ?array
    {
        if (!$masterFamily) {
            return null;
        }

        return match ($routeName) {
            'grp.masters.master_families.show' => [
                'label' => $masterFamily->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'masterFamily' => $masterFamily->slug
                    ]
                ]
            ],
            'grp.masters.master_shops.show.master_families.show' => [
                'label' => $masterFamily->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'masterShop'   => $masterFamily->masterShop->slug,
                        'masterFamily' => $masterFamily->slug
                    ]
                ]
            ],
            default => [] // Add a default case to handle unmatched route names
        };
    }
}
