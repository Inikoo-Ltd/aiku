<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 26-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Actions\OrgAction;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use App\Models\SysAdmin\Group;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\Actions\Helpers\Language\UI\GetLanguagesOptions;

class EditMasterFamily extends OrgAction
{
    private MasterShop|MasterProductCategory|Group $parent;

    public function asController(MasterShop $masterShop, MasterProductCategory $masterFamily, ActionRequest $request): Response
    {
        $this->parent = $masterShop;
        $group        = group();
        $this->initialisationFromGroup($group, $request);

        return $this->handle($masterFamily, $request);
    }

    public function inMasterDepartment(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterProductCategory $masterFamily, ActionRequest $request): Response
    {
        $this->parent = $masterDepartment;
        $group        = group();
        $this->initialisationFromGroup($group, $request);

        return $this->handle($masterFamily, $request);
    }

    public function inMasterSubDepartment(MasterShop $masterShop, MasterProductCategory $masterSubDepartment, MasterProductCategory $masterFamily, ActionRequest $request): Response
    {
        $this->parent = $masterSubDepartment;
        $group        = group();
        $this->initialisationFromGroup($group, $request);

        return $this->handle($masterFamily, $request);
    }

    public function inMasterSubDepartmentInMasterDepartment(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterProductCategory $masterSubDepartment, MasterProductCategory $masterFamily, ActionRequest $request): Response
    {
        $this->parent = $masterSubDepartment;
        $group        = group();
        $this->initialisationFromGroup($group, $request);

        return $this->handle($masterFamily, $request);
    }

    public function handle(MasterProductCategory $masterProductCategory, ActionRequest $request): Response
    {
        $departmentIdFormData = [];

        if ($masterProductCategory->parent?->type == MasterProductCategoryTypeEnum::DEPARTMENT) {
            $departmentIdFormData['master_department_id'] = [
                'type'     => 'select',
                'label'    => __('Master Department'),
                'required' => true,
                'options'  => $masterProductCategory->masterShop->masterProductCategories()
                    ->where('type', MasterProductCategoryTypeEnum::DEPARTMENT)
                    ->get(['id as value', 'name as label'])
                    ->toArray(),
                'value'   =>  $masterProductCategory->parent_id,
            ];

        }

        return Inertia::render(
            'EditModel',
            [
                'warning' => $masterProductCategory->productCategories ? [
                    'type'  =>  'warning',
                    'title' =>  'warning',
                    'text'  =>  __('Changing name or description may affect multiple families in various shops.'),
                    'icon'  => ['fas', 'fa-exclamation-triangle']
                ] : null,
                 'breadcrumbs' => $this->getBreadcrumbs(
                     $this->parent,
                     $masterProductCategory,
                     $request->route()->getName(),
                     $request->route()->originalParameters()
                 ),
                'title'       => __('Edit Master Family'),
                'pageHead'    => [
                    'title'   => __('edit master family'),
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
                                    'language_from' => 'en',
                                    'full' => true,
                                    'main' => $masterProductCategory->name,
                                    'languages' => GetLanguagesOptions::make()->getExtraGroupLanguages($masterProductCategory->group->extra_languages),
                                    'value' => $masterProductCategory->getTranslations('name_i8n')
                                ],
                                'description_title_i8n' => [
                                    'type'  => 'input_translation',
                                    'label' => __('translate description title'),
                                    'language_from' => 'en',
                                    'full' => true,
                                    'main' => $masterProductCategory->description_title,
                                    'languages' => GetLanguagesOptions::make()->getExtraGroupLanguages($masterProductCategory->group->extra_languages),
                                    'value' => $masterProductCategory->getTranslations('description_title_i8n')
                                ],
                                'description_i8n' => [
                                    'type'  => 'textEditor_translation',
                                    'label' => __('translate description'),
                                    'language_from' => 'en',
                                    'full' => true,
                                    'main' => $masterProductCategory->description,
                                    'languages' => GetLanguagesOptions::make()->getExtraGroupLanguages($masterProductCategory->group->extra_languages),
                                    'value' => $masterProductCategory->getTranslations('description_i8n')
                                ],
                                'description_extra_i8n' => [
                                    'type'  => 'textEditor_translation',
                                    'label' => __('translate description extra'),
                                    'language_from' => 'en',
                                    'full' => true,
                                    'main' => $masterProductCategory->description_extra,
                                    'languages' => GetLanguagesOptions::make()->getExtraGroupLanguages($masterProductCategory->group->extra_languages),
                                    'value' => $masterProductCategory->getTranslations('description_extra_i8n')
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
                                    'value'         => $masterProductCategory->cost_price_ratio,
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
                                    "value"   => $masterProductCategory->imageSources(720, 480),
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
                                'department_id'  =>  [
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


    public function getBreadcrumbs(MasterShop|Group|MasterProductCategory $parent, MasterProductCategory $masterFamily, string $routeName, array $routeParameters): array
    {
        return ShowMasterFamily::make()->getBreadcrumbs(
            masterFamily: $masterFamily,
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '('.__('Editing').')'
        );
    }
}
