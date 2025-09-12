<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 26-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Actions\Masters\MasterShop\UI\IndexMasterShops;
use App\Actions\OrgAction;
use App\Enums\UI\SupplyChain\MasterSubDepartmentTabsEnum;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\Actions\Helpers\Language\UI\GetLanguagesOptions;

class EditMasterSubDepartment extends OrgAction
{
    /**
     * @var \App\Models\Masters\MasterProductCategory|\App\Models\Masters\MasterShop
     */
    private MasterProductCategory|MasterShop $parent;

    public function inMasterDepartment(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterProductCategory $masterSubDepartment, ActionRequest $request): Response
    {
        $this->parent = $masterDepartment;
        $group        = group();
        $this->initialisationFromGroup($group, $request)->withTab(MasterSubDepartmentTabsEnum::values());

        return $this->handle($masterSubDepartment, $request);
    }

    public function asController(MasterShop $masterShop, MasterProductCategory $masterSubDepartment, ActionRequest $request): Response
    {
        $this->parent = $masterSubDepartment;
        $group        = group();
        $this->initialisationFromGroup($group, $request)->withTab(MasterSubDepartmentTabsEnum::values());

        return $this->handle($masterSubDepartment, $request);
    }

    public function handle(MasterProductCategory $masterProductCategory, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'warning' => $masterProductCategory->productCategories ? [
                    'type'  =>  'warning',
                    'title' =>  'warning',
                    'text'  =>  __('Changing name or description may affect multiple sub departments in various shops.'),
                    'icon'  => ['fas', 'fa-exclamation-triangle']
                ] : null,
                 'breadcrumbs' => $this->getBreadcrumbs(
                     $masterProductCategory,
                     $request->route()->getName(),
                     $request->route()->originalParameters()
                 ),
                'title'       => __('Edit Master Sub-department'),
                'pageHead'    => [
                    'title'   => __('edit master Sub-department'),
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('cancel'),
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ]
                ],
                'formData'    => [
                    'blueprint' => [
                        [
                            'label'  => __('Id'),
                            'icon'   => 'fa-light fa-fingerprint',
                            'fields' => [
                                'code' => [
                                    'type'  => 'input',
                                    'label' => __('code'),
                                    'value' => $masterProductCategory->code
                                ],
                            ]
                        ],
                        [
                            'label'  => __('Name/Description'),
                            'icon'   => 'fa-light fa-tag',
                            'title'  => __('id'),
                            'fields' => [
                                'name' => [
                                    'type'  => 'input',
                                    'label' => __('name'),
                                    'value' => $masterProductCategory->name
                                ],
                                'description_title' => [
                                    'type'  => 'input',
                                    'label' => __('description title'),
                                    'value' => $masterProductCategory->description_title
                                ],
                                'description' => [
                                    'type'  => 'textEditor',
                                    'label' => __('description'),
                                    'value' => $masterProductCategory->description
                                ],
                                'description_extra' => [
                                    'type'  => 'textEditor',
                                    'label' => __('Extra description'),
                                    'value' => $masterProductCategory->description_extra
                                ],
                            ]
                        ],
                        [
                            'label'  => __('Translations'),
                            'icon'   => 'fa-light fa-language',
                            'fields' => [
                                'name_i8n' => [
                                    'type'  => 'input_translation',
                                    'label' => __('translate name'),
                                    'languages' => GetLanguagesOptions::make()->getExtraGroupLanguages($masterProductCategory->group->extra_languages),
                                    'value' => $masterProductCategory->getTranslations('name_i8n')
                                ],
                                'description_title_i8n' => [
                                    'type'  => 'input_translation',
                                    'label' => __('translate description title'),
                                    'languages' => GetLanguagesOptions::make()->getExtraGroupLanguages($masterProductCategory->group->extra_languages),
                                    'value' => $masterProductCategory->getTranslations('description_title_i8n')
                                ],
                                'description_i8n' => [
                                    'type'  => 'textEditor_translation',
                                    'label' => __('translate description'),
                                    'languages' => GetLanguagesOptions::make()->getExtraGroupLanguages($masterProductCategory->group->extra_languages),
                                    'value' => $masterProductCategory->getTranslations('description_i8n')
                                ],
                                'description_extra_i8n' => [
                                    'type'  => 'textEditor_translation',
                                    'label' => __('translate description extra'),
                                    'languages' => GetLanguagesOptions::make()->getExtraGroupLanguages($masterProductCategory->group->extra_languages),
                                    'value' => $masterProductCategory->getTranslations('description_extra_i8n')
                                ],
                            ]
                        ],
                        [
                            'label'  => __('Properties'),
                            'icon'   => 'fa-light fa-fingerprint',
                            'fields' => [
                                "image"         => [
                                    "type"    => "crop-image-full",
                                    "label"   => __("Image"),
                                    "value"   => $masterProductCategory->imageSources(720, 480),
                                    "required" => false,
                                    'noSaveButton' => true,
                                    "full"         => true
                                ]
                            ]
                        ],
                        [
                            'label'  => __('Pricing'),
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
                                    'value'         => $masterProductCategory->cost_price_ratio,
                                    'min'           => 0
                                ],
                            ]
                        ],
                        [
                            'label'  => __('Master Department'),
                            'icon'   => 'fa-light fa-box',
                            'fields' => [
                                'master_department_id'  =>  [
                                    'type'    => 'select_infinite',
                                    'label'   => __('Master Department'),
                                    'options'   => [
                                        [
                                            'id' => $masterProductCategory->masterDepartment?->id,
                                            'code' => $masterProductCategory->masterDepartment?->code
                                        ]
                                    ],
                                    'fetchRoute'    => [
                                        'name'       => 'grp.masters.master_shops.show.master_departments.index',
                                        'parameters' => [
                                            'masterShop' => $masterProductCategory->masterShop->slug,
                                        ]
                                    ],
                                    'valueProp' => 'id',
                                    'labelProp' => 'code',
                                    'required' => false,
                                    'value'   => $masterProductCategory->masterDepartment->id ?? null,
                                ]
                            ],

                        ],
                    ],
                    'args'      => [
                        'updateRoute' => [
                            'name' => 'grp.models.master_product.update',
                            'parameters' => [
                                'masterProductCategory' => $masterProductCategory->id
                            ]
                        ],
                    ],
                ]
            ]
        );
    }


    public function getBreadcrumbs(MasterProductCategory $masterSubDepartment, string $routeName, array $routeParameters): array
    {
        return array_merge(
            match ($this->parent::class) {
                MasterShop::class => IndexMasterShops::make()->getBreadcrumbs(),
                MasterProductCategory::class => ShowMasterSubDepartment::make()->getBreadcrumbs(
                    masterSubDepartment: $masterSubDepartment,
                    routeName: $routeName,
                    routeParameters: $routeParameters,
                )
            },
            [
                [
                    'type' => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Editing sub-department'),
                    ]
                ]
            ]
        );
    }
}
