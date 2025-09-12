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
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use App\Models\SysAdmin\Group;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\Actions\Helpers\Language\UI\GetLanguagesOptions;

class EditMasterDepartment extends OrgAction
{
    private MasterShop|Group $parent;

    public function asController(MasterShop $masterShop, MasterProductCategory $masterDepartment, ActionRequest $request): Response
    {
        $this->parent = $masterShop;
        $group        = group();
        $this->initialisationFromGroup($group, $request);

        return $this->handle($masterDepartment, $request);
    }

    public function handle(MasterProductCategory $masterProductCategory, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'warning' => $masterProductCategory->productCategories ? [
                    'type'  =>  'warning',
                    'title' =>  'warning',
                    'text'  =>  __('Changing name or description may affect multiple departments in various shops.'),
                    'icon'  => ['fas', 'fa-exclamation-triangle']
                ] : null,
                 'breadcrumbs' => $this->getBreadcrumbs(
                     $this->parent,
                     $masterProductCategory,
                     $request->route()->getName(),
                     $request->route()->originalParameters()
                 ),
                'title'       => __('Edit Master Department'),
                'pageHead'    => [
                    'title'   => __('edit master department'),
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
                                ],
                            ]
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


    public function getBreadcrumbs(MasterShop|Group $parent, MasterProductCategory $masterDepartment, string $routeName, array $routeParameters): array
    {
        return ShowMasterDepartment::make()->getBreadcrumbs(
            parent: $parent,
            masterDepartment: $masterDepartment,
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '('.__('Editing').')'
        );
    }
}
