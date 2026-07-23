<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 26-05-2025, Bali, Indonesia
 * GitHub: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Actions\Goods\TradeUnitFamily\GetTradeUnitFamilyForFamilies;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditMasterFamily extends OrgAction
{
    use WithMastersEditAuthorisation;
    use WithMasterFamilyNavigation;

    public function asController(MasterShop $masterShop, MasterProductCategory $masterFamily, ActionRequest $request): Response
    {
        $group = group();
        $this->initialisationFromGroup($group, $request);

        return $this->handle($masterFamily, $request);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterDepartment(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterProductCategory $masterFamily, ActionRequest $request): Response
    {
        $group = group();
        $this->initialisationFromGroup($group, $request);

        return $this->handle($masterFamily, $request);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterSubDepartment(MasterShop $masterShop, MasterProductCategory $masterSubDepartment, MasterProductCategory $masterFamily, ActionRequest $request): Response
    {
        $group = group();
        $this->initialisationFromGroup($group, $request);

        return $this->handle($masterFamily, $request);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterSubDepartmentInMasterDepartment(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterProductCategory $masterSubDepartment, MasterProductCategory $masterFamily, ActionRequest $request): Response
    {
        $group = group();
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
                'value'    => $masterProductCategory->master_department_id,
            ];
        }

        return Inertia::render(
            'EditModel',
            [
                'warning'     => $masterProductCategory->productCategories ? [
                    'type'  => 'warning',
                    'title' => __('Important'),
                    'text'  => __('Changes to this master name or descriptions will overwrite child product names and descriptions where “Follow Master” is enabled.'),
                    'icon'  => ['fas', 'fa-exclamation-triangle']
                ] : null,
                'breadcrumbs' => $this->getBreadcrumbs(
                    $masterProductCategory,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPreviousModel($masterProductCategory, $request),
                    'next'     => $this->getNextModel($masterProductCategory, $request),
                ],
                'title'       => __('Edit Master Family'),
                'pageHead'    => [
                    'title'   => __('Edit master family'),
                    'actions' => [
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
                'formData'    => [
                    'blueprint' => [
                        [
                            'label'  => __('Id'),
                            'icon'   => 'fa-light fa-fingerprint',
                            'fields' => [
                                'code' => [
                                    'type'  => 'input',
                                    'label' => __('Code'),
                                    'value' => $masterProductCategory->code
                                ],
                            ]
                        ],
                        [
                            'label'  => __('Name/Description'),
                            'icon'   => 'fa-light fa-tag',
                            'fields' => [
                                'name'              => [
                                    'type'  => 'input',
                                    'label' => __('Name'),
                                    'value' => $masterProductCategory->name
                                ],
                                'description_title' => [
                                    'type'    => 'input',
                                    'label'   => __('Description title'),
                                    'options' => [
                                        'counter' => true,
                                    ],
                                    'value'   => $masterProductCategory->description_title
                                ],
                                'description'       => [
                                    'type'    => 'textEditor',
                                    'label'   => __('Description'),
                                    'options' => [
                                        'counter' => true,
                                    ],
                                    'toogle'  => [
                                        'bold', 'italic', 'underline', 'bulletList','customLink', 'undo', 'redo', 'highlight', 'color', 'clear'
                                    ],
                                    'value'   => $masterProductCategory->description
                                ],
                                'description_extra' => [
                                    'type'    => 'textEditor',
                                    'label'   => __('Extra description'),
                                    'options' => [
                                        'counter' => true,
                                    ],
                                    'toogle'  => [
                                        'bold', 'italic', 'underline', 'bulletList','customLink', 'undo', 'redo', 'highlight', 'color', 'clear'
                                    ],
                                    'value'   => $masterProductCategory->description_extra
                                ],
                            ]
                        ],
                        [
                            'label'  => __('Pricing'),
                            'title'  => __('id'),
                            'icon'   => 'fa-light fa-money-bill',
                            'fields' => [
                                'cost_price_ratio' => [
                                    'type'        => 'input_number',
                                    'bind'        => [
                                        'maxFractionDigits' => 3
                                    ],
                                    'label'       => __('Pricing ratio'),
                                    'placeholder' => __('Cost price ratio'),
                                    'required'    => true,
                                    'value'       => $masterProductCategory->cost_price_ratio,
                                    'min'         => 0
                                ]
                            ]
                        ],
                        [
                            'label'  => __('Parent').' ('.__('Department/Sub-Department').')',
                            'icon'   => 'fa-light fa-folder-tree',
                            'fields' => [
                                'master_department_or_master_sub_department_id' => [
                                    'type'       => 'select_infinite',
                                    'label'      => __('Parent'),
                                    'options'    => [
                                        [
                                            'id'   => $masterProductCategory->masterSubDepartment->id ?? $masterProductCategory->masterDepartment->id ?? null,
                                            'code' => $masterProductCategory->masterSubDepartment->code ?? $masterProductCategory->masterDepartment->code ?? null,
                                            'type' => $masterProductCategory->masterSubDepartment->type ?? $masterProductCategory->masterDepartment->type ?? null,
                                        ]
                                    ],
                                    'fetchRoute' => [
                                        'name'       => 'grp.json.master_shop.master_departments_and_sub_departments',
                                        'parameters' => [
                                            'masterShop' => $masterProductCategory->masterShop->slug,
                                        ]
                                    ],
                                    'required'   => true,
                                    'valueProp'  => 'id',
                                    'type_label' => 'department-and-sub-department',
                                    'labelProp'  => 'code',
                                    'value'      => $masterProductCategory->masterSubDepartment->id ?? $masterProductCategory->masterDepartment->id ?? null,
                                ],


                            ],

                        ],
                        [
                            'label'  => __('Trade Unit Family'),
                            'icon'   => 'fa-light fa-folder-tree',
                            'fields' => [
                                'trade_unit_family_id' => [
                                    'label'         => 'Trade Unit Family',
                                    'placeholder'   => __('Select a Trade Unit Family'),
                                    'information'   => __('Would link this family to the selected trade unit family'),
                                    'type'          => 'select',
                                    'options'       => GetTradeUnitFamilyForFamilies::run($masterProductCategory),
                                    'required'      => true,
                                    'searchable'    => true,
                                    'mode'          => 'single',
                                    'value'         => $masterProductCategory->trade_unit_family_id
                                ],
                            ]
                        ],
                        $masterProductCategory->masterShop->gold_reward_eligible ? [
                            'label'  => __('Vol / GR'),
                            'icon'   => 'fa-light fa-badge-percent',
                            'fields' => [
                                'vol_gr_offer' => [
                                    'label'         => 'Vol / GR',
                                    'information'   => __('Any changes will affect the offer in all shops.'),
                                    'type'          => 'vol_discount',
                                    'initial_value' => [
                                        'item_quantity'  => $masterProductCategory->gr_vol_discount_quantity,
                                        'percentage_off' => $masterProductCategory->gr_vol_discount_percentage,
                                    ],
                                ],
                            ],
                        ] : [],
                        [
                            'label'  => __('FAQ'),
                            'icon'   => 'fa-light fa-question-circle',
                            'fields' => [
                                'faq' => [
                                    'type'  => 'faq-master',
                                    'label' => __('FAQ'),
                                    'value' => $masterProductCategory->faq,
                                ],
                            ]
                        ],
                    ],
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.master_product_category.update',
                            'parameters' => [
                                'masterProductCategory' => $masterProductCategory->id
                            ]
                        ],
                    ],
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
            suffix: '('.__('Editing').')'
        );
    }
}
