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
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use App\Models\SysAdmin\Group;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditMasterDepartment extends OrgAction
{
    use WithMastersEditAuthorisation;
    use WithMasterDepartmentNavigation;

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
                'warning'     => $masterProductCategory->productCategories ? [
                    'type'  => 'warning',
                    'title' => __('Important'),
                    'text'  => __('Changes to this master name or descriptions will overwrite child product names and descriptions where “Follow Master” is enabled.'),
                    'icon'  => ['fas', 'fa-exclamation-triangle']
                ] : null,
                 'breadcrumbs' => $this->getBreadcrumbs(
                     $this->parent,
                     $masterProductCategory,
                     $request->route()->getName(),
                     $request->route()->originalParameters()
                 ),
                'navigation'  => [
                    'previous' => $this->getPreviousModel($masterProductCategory, $request),
                    'next'     => $this->getNextModel($masterProductCategory, $request),
                ],
                'title'       => __('Edit master department').' '.$masterProductCategory->code,
                'pageHead'    => [
                    'title'   => __('Edit master department'),
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('Exit edit'),
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
                                'name' => [
                                    'type'  => 'input',
                                    'label' => __('Name'),
                                    'value' => $masterProductCategory->name
                                ],
                                'description' => [
                                    'type'  => 'textEditor',
                                    'label' => __('Description'),
                                    'options'   => [
                                        'counter'   => true,
                                    ],
                                    'toogle'  => [
                                          'heading2', 'heading3', 'fontSize', 'bold', 'italic', 'underline', 'bulletList', "fontFamily",
                                          'orderedList', 'blockquote', 'divider', 'alignLeft', 'alignRight', "link",
                                          'alignCenter', 'undo', 'redo', 'highlight', 'color', 'clear'
                                    ],
                                    'value' => $masterProductCategory->description
                                ],
                                'description_extra' => [
                                    'type'  => 'textEditor',
                                    'label' => __('Extra description'),
                                    'options'   => [
                                        'counter'   => true,
                                    ],
                                    'toogle'  => [
                                          'heading2', 'heading3', 'fontSize', 'bold', 'italic', 'underline', 'bulletList', "fontFamily",
                                          'orderedList', 'blockquote', 'divider', 'alignLeft', 'alignRight', "link",
                                          'alignCenter', 'undo', 'redo', 'highlight', 'color', 'clear'
                                    ],
                                    'value' => $masterProductCategory->description_extra
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
                                    'label'         => __('Pricing ratio'),
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
                            'name' => 'grp.models.master_product_category.update',
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
