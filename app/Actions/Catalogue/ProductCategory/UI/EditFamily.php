<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Apr 2023 16:36:34 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory\UI;

use App\Actions\Helpers\Language\UI\GetLanguagesOptions;
use App\Actions\OrgAction;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\UI\Catalogue\DepartmentTabsEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\Http\Resources\Helpers\LanguageResource;

class EditFamily extends OrgAction
{
    public function handle(ProductCategory $family): ProductCategory
    {
        return $family;
    }



    public function inOrganisation(Organisation $organisation, ProductCategory $family, ActionRequest $request): ProductCategory
    {
        $this->initialisation($organisation, $request);

        return $this->handle($family);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inShop(Organisation $organisation, Shop $shop, ProductCategory $family, ActionRequest $request): ProductCategory
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($family);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inDepartment(Organisation $organisation, Shop $shop, ProductCategory $department, ProductCategory $family, ActionRequest $request): ProductCategory
    {
        $this->initialisationFromShop($shop, $request)->withTab(DepartmentTabsEnum::values());

        return $this->handle($family);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inSubDepartment(Organisation $organisation, Shop $shop, ProductCategory $department, ProductCategory $subDepartment, ProductCategory $family, ActionRequest $request): ProductCategory
    {

        $this->initialisationFromShop($shop, $request)->withTab(DepartmentTabsEnum::values());

        return $this->handle($family);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inSubDepartmentInShop(Organisation $organisation, Shop $shop, ProductCategory $subDepartment, ProductCategory $family, ActionRequest $request): ProductCategory
    {

        $this->initialisationFromShop($shop, $request)->withTab(DepartmentTabsEnum::values());

        return $this->handle($family);
    }

    public function htmlResponse(ProductCategory $family, ActionRequest $request): Response
    {
        $departmentIdFormData = [];

        if ($family->parent?->type == ProductCategoryTypeEnum::DEPARTMENT) {
            $departmentIdFormData['department_id'] = [
                'type'     => 'select',
                'label'    => __('Department'),
                'required' => true,
                'options'  => $family->shop->productCategories()
                    ->where('type', ProductCategoryTypeEnum::DEPARTMENT)
                    ->get(['id as value', 'name as label'])
                    ->toArray(),
                'value'   =>  $family->parent_id,
            ];

        }

         $urlMaster                              = null;
        if ($family->master_product_category_id) {
            $urlMaster = [
                'name'       => 'grp.helpers.redirect_master_product_category',
                'parameters' => [
                    $family->masterProductCategory->id
                ]
            ];
        }
        $languages = [$family->shop->language_id => LanguageResource::make($family->shop->language)->resolve()];
        return Inertia::render(
            'EditModel',
            [
                'title'       => __('family'),
                'warning' => $family->masterProductCategory ? [
                    'type'  =>  'warning',
                    'title' =>  'warning',
                    'text'  =>  __('Changing name or description may affect master family.'),
                    'icon'  => ['fas', 'fa-exclamation-triangle']
                ] : null,
                'iconRight' => $urlMaster  ?  [
                        'icon'  => "fab fa-octopus-deploy",
                        'color' => "#4B0082",
                        'class' => 'opacity-70 hover:opacity-100',
                        'url'   => $urlMaster
                    ] : [],
                'breadcrumbs' => $this->getBreadcrumbs(
                    $family,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'                            => [
                    'previous' => $this->getPrevious($family, $request),
                    'next'     => $this->getNext($family, $request),
                ],
                'pageHead'    => [
                    'title'    => $family->code,
                    'actions'  => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ]
                ],

                'formData' => [
                    'blueprint' => array_filter(
                        [
                            [
                                'label'  => __('Id'),
                                'icon'   => 'fa-light fa-fingerprint',
                                'fields' => [
                                    'code' => [
                                        'type'  => 'input',
                                        'label' => __('code'),
                                        'value' => $family->code
                                    ],
                                ]
                            ],
                            [
                                'label'  => __('Name/Description'),
                                'icon'   => 'fa-light fa-tag',
                                'fields' => [
                                    'code' => [
                                        'type'  => 'input',
                                        'label' => __('code'),
                                        'value' => $family->code
                                    ],
                                    'name' => $family->masterProductCategory ? [
                                        'type'  => 'input_translation',
                                        'label' => __('name'),
                                        'language_from' => 'en',
                                        'full' => true,
                                        'main' => $family->masterProductCategory->name,
                                        'languages' => $languages,
                                        'value' => $family->getTranslations('name_i8n'),
                                        'mode' => 'single'
                                    ] : [
                                        'type'  => 'input',
                                        'label' => __('name'),
                                        'value' => $family->name
                                    ],
                                    'description_title' => $family->masterProductCategory ? [
                                        'type'  => 'input_translation',
                                        'label' => __('description title'),
                                        'language_from' => 'en',
                                        'full' => true,
                                        'main' => $family->masterProductCategory->description_title,
                                        'languages' => $languages,
                                        'mode' => 'single',
                                        'value' => $family->getTranslations('description_title_i8n')
                                    ] : [
                                         'type'  => 'input',
                                         'label' => __('description title'),
                                         'value' => $family->description_title
                                    ],
                                    'description' => $family->masterProductCategory ? [
                                        'type'  => 'textEditor_translation',
                                        'label' => __('description'),
                                        'language_from' => 'en',
                                        'full' => true,
                                        'main' => $family->masterProductCategory->description,
                                        'languages' => $languages,
                                        'mode' => 'single',
                                        'value' => $family->getTranslations('description_i8n')
                                    ] : [
                                        'type'  => 'textEditor',
                                        'label' => __('description'),
                                        'value' => $family->description
                                    ],
                                    'description_extra' => $family->masterProductCategory ? [
                                        'type'  => 'textEditor_translation',
                                        'label' => __('Extra description'),
                                        'language_from' => 'en',
                                        'full' => true,
                                        'main' => $family->masterProductCategory->description_extra,
                                        'languages' => $languages,
                                        'mode' => 'single',
                                        'value' => $family->getTranslations('description_extra_i8n')
                                    ] : [
                                        'type'  => 'textEditor',
                                        'label' => __('Extra description'),
                                        'value' => $family->description_extra
                                    ],
                                ]
                            ],
                            [
                                'label'  => __('Pricing'),
                                'title'  => __('id'),
                                'icon'   => 'fa-light fa-money-bill',
                                'fields' => [
                                    'cost_price_ratio' => [
                                        'type'          => 'input_number',
                                        'bind' => [
                                            'maxFractionDigits' => 3
                                        ],
                                        'label'         => __('pricing ratio'),
                                        'placeholder'   => __('Cost price ratio'),
                                        'required'      => true,
                                        'value'         => $family->cost_price_ratio,
                                        'min'           => 0
                                    ]
                                ]
                            ],
                            [
                                'label'  => __('Image'),
                                'icon'   => 'fa-light fa-image',
                                'title'  => __('Media'),
                                'fields' => [
                                    "image"         => [
                                        "type"    => "crop-image-full",
                                        "label"   => __("Image"),
                                        "value"   => $family->imageSources(720, 480),
                                        "required" => false,
                                        'noSaveButton' => true,
                                        "full"         => true
                                    ],
                                    ...$departmentIdFormData
                                ]
                            ],
                            [
                                'label'  => __('Parent').' ('.__('Department/Sub-Department').')',
                                'icon'   => 'fa-light fa-folder-tree',
                                'fields' => [
                                    'department_or_sub_department_id'  =>  [
                                        'type'    => 'select_infinite',
                                        'label'   => __('Parent'),
                                        'options'   => [
                                                [
                                                    'id' =>  $family->subDepartment->id ?? $family->department->id  ?? null,
                                                    'code' =>  $family->subDepartment->code ?? $family->department->code  ?? null
                                                ]
                                        ],
                                        'fetchRoute'    => [
                                            'name'       => 'grp.json.shop.department_and_sub_departments',
                                            'parameters' => [
                                                'shop' => $family->shop->slug,
                                            ]
                                        ],
                                        'valueProp' => 'id',
                                        'labelProp' => 'code',
                                        'required' => false,
                                        'value'   => $family->subDepartment->id ?? $family->department->id  ?? null,
                                    ],



                                ],

                            ],
                        ],
                    ),
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.product_category.update',
                            'parameters' => [
                                'productCategory'   => $family->id
                            ]
                        ],
                    ]
                ]
            ]
        );
    }


    public function getBreadcrumbs(ProductCategory $family, string $routeName, array $routeParameters): array
    {
        return ShowFamily::make()->getBreadcrumbs(
            $family,
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '(' . __('Editing') . ')'
        );
    }

    public function getPrevious(ProductCategory $family, ActionRequest $request): ?array
    {
        $previous = ProductCategory::where('code', '<', $family->code)->orderBy('code', 'desc')->first();
        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(ProductCategory $family, ActionRequest $request): ?array
    {
        $next = ProductCategory::where('code', '>', $family->code)->orderBy('code')->first();
        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?ProductCategory $family, string $routeName): ?array
    {
        if (!$family) {
            return null;
        }
        return match ($routeName) {
            'shops.families.edit' => [
                'label' => $family->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'department' => $family->slug
                    ]
                ]
            ],
            'shops.show.families.edit' => [
                'label' => $family->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'shop'       => $family->shop->slug,
                        'department' => $family->slug
                    ]
                ]
            ],
            default => []
        };
    }
}
