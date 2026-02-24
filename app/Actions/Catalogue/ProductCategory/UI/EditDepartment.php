<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Apr 2023 16:36:34 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueEditAuthorisation;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use App\Http\Resources\Helpers\LanguageResource;
use Lorisleiva\Actions\ActionRequest;

class EditDepartment extends OrgAction
{
    use WithCatalogueEditAuthorisation;
    use WithDepartmentNavigation;

    public function handle(ProductCategory $department): ProductCategory
    {
        return $department;
    }


    public function inOrganisation(Organisation $organisation, ProductCategory $department, ActionRequest $request): ProductCategory
    {
        $this->initialisation($organisation, $request);

        return $this->handle($department);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inShop(Organisation $organisation, Shop $shop, ProductCategory $department, ActionRequest $request): ProductCategory
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($department);
    }

    public function htmlResponse(ProductCategory $department, ActionRequest $request): Response
    {
        $urlMaster = null;
        if ($department->master_product_category_id) {
            $urlMaster = [
                'name'       => 'grp.helpers.redirect_master_product_category',
                'parameters' => [
                    $department->masterProductCategory->id
                ]
            ];
        }
        $languages = [$department->shop->language_id => LanguageResource::make($department->shop->language)->resolve()];

        $warning = [];
        $forceFollowMasterDepartment = data_get($department->shop->settings, 'catalog.department_follow_master');

        if ($department->masterProductCategory && $forceFollowMasterDepartment) {
            $warning = [
                'warning'     => [
                    'type'  => 'warning',
                    'title' => 'Warning',
                    // 'text'  => __('Changing name or description may affect master department .'), // Isn't true anymore. Not neccessarily the case. Turned off
                    'text'  => __('This shop has enabled the Department force follow master setting. Updates made on master will overwrite local changes'),
                    'icon'  => ['fas', 'fa-exclamation-triangle'],
                ]
            ];
        }

        return Inertia::render(
            'EditModel',
            [
                'title'       => __('department'),
                ...$warning,
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPreviousModel($department, $request),
                    'next'     => $this->getNextModel($department, $request),
                ],
                'pageHead'    => [
                    'title'     => $department->name,
                    'iconRight' => $urlMaster ? [
                        'icon'  => "fab fa-octopus-deploy",
                        'color' => "#4B0082",
                        'class' => 'opacity-70 hover:opacity-100',
                        'url'   => $urlMaster
                    ] : [],
                    'icon'      =>
                        [
                            'icon'  => ['fal', 'fa-folder-tree'],
                            'title' => __('Department')
                        ],
                    'actions'   => [
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
                    'blueprint' =>
                        array_filter(
                            [
                                [
                                    'label'  => __('Id'),
                                    'icon'   => 'fa-light fa-fingerprint',
                                    'fields' => [
                                        'code' => [
                                            'type'  => 'input',
                                            'label' => __('Code'),
                                            'value' => $department->code
                                        ],
                                    ]
                                ],
                                [
                                    'label'  => __('Name/Description'),
                                    'icon'   => 'fa-light fa-tag',
                                    'fields' => [
                                        'name'              => $department->masterProductCategory
                                            ? [
                                                'type'          => 'input_translation',
                                                'label'         => __('Name'),
                                                'language_from' => 'en',
                                                'full'          => true,
                                                'main'          => $department->masterProductCategory->name,
                                                'languages'     => $languages,
                                                'mode'          => 'single',
                                                'value'         => $department->name,
                                                'reviewed'      => $department->is_name_reviewed
                                            ]
                                            : [
                                                'type'  => 'input',
                                                'label' => __('Name'),
                                                'value' => $department->name
                                            ],
                                      /*   'description_title' => $department->masterProductCategory
                                            ? [
                                                'type'          => 'input_translation',
                                                'label'         => __('description title'),
                                                'language_from' => 'en',
                                                'full'          => true,
                                                'main'          => $department->masterProductCategory->description_title,
                                                'languages'     => $languages,
                                                'mode'          => 'single',
                                                'value'         => $department->description_title,
                                                'reviewed'      => $department->is_description_title_reviewed

                                            ]
                                            : [
                                                'type'  => 'input',
                                                'label' => __('description title'),
                                                'value' => $department->description_title
                                            ], */
                                        'description'       => $department->masterProductCategory
                                            ? [
                                                'type'          => 'textEditor_translation',
                                                'label'         => __('Description'),
                                                'language_from' => 'en',
                                                'full'          => true,
                                                'main'          => $department->masterProductCategory->description,
                                                'languages'     => $languages,
                                                'mode'          => 'single',
                                                'value'         => $department->description,
                                                'reviewed'      => $department->is_description_reviewed,
                                                'toogle'  => [
                                                        'heading2', 'heading3', 'fontSize', 'bold', 'italic', 'underline', 'bulletList', "fontFamily",
                                                        'orderedList', 'blockquote', 'divider', 'alignLeft', 'alignRight', "customLink",
                                                        'alignCenter', 'undo', 'redo', 'highlight', 'color', 'clear'
                                                ],
                                            ]
                                            : [
                                                'type'  => 'textEditor',
                                                'label' => __('Description'),
                                                'value' => $department->description,
                                                'options' => [
                                                    'counter' => true,
                                                ],
                                                'toogle'  => [
                                                        'heading2', 'heading3', 'fontSize', 'bold', 'italic', 'underline', 'bulletList', "fontFamily",
                                                        'orderedList', 'blockquote', 'divider', 'alignLeft', 'alignRight', "customLink",
                                                        'alignCenter', 'undo', 'redo', 'highlight', 'color', 'clear'
                                                ],
                                            ],
                                        'description_extra' => $department->masterProductCategory
                                            ? [
                                                'type'          => 'textEditor_translation',
                                                'label'         => __('Extra description'),
                                                'language_from' => 'en',
                                                'full'          => true,
                                                'main'          => $department->masterProductCategory->description_extra,
                                                'languages'     => $languages,
                                                'mode'          => 'single',
                                                'value'         => $department->description_extra,
                                                'reviewed'      => $department->is_description_extra_reviewed,
                                                'toogle'  => [
                                                        'heading2', 'heading3', 'fontSize', 'bold', 'italic', 'underline', 'bulletList', "fontFamily",
                                                        'orderedList', 'blockquote', 'divider', 'alignLeft', 'alignRight', "customLink",
                                                        'alignCenter', 'undo', 'redo', 'highlight', 'color', 'clear'
                                                ]
                                            ]
                                            : [
                                                'type'  => 'textEditor',
                                                'label' => __('Extra description'),
                                                'value' => $department->description_extra,
                                                'options' => [
                                                    'counter' => true,
                                                ],
                                                'toogle'  => [
                                                        'heading2', 'heading3', 'fontSize', 'bold', 'italic', 'underline', 'bulletList', "fontFamily",
                                                        'orderedList', 'blockquote', 'divider', 'alignLeft', 'alignRight', "customLink",
                                                        'alignCenter', 'undo', 'redo', 'highlight', 'color', 'clear'
                                                ],
                                            ],
                                    ]
                                ],
                                [
                                    'label'  => __('Pricing'),
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
                                            'value'       => $department->cost_price_ratio,
                                            'min'         => 0
                                        ],
                                    ]
                                ],
                                [
                                    'label'  => __('Properties'),
                                    'icon'   => 'fa-light fa-fingerprint',
                                    'fields' => [
                                        'follow_master' => [
                                            'type'  => 'toggle',
                                            'label' => __('Follow Master'),
                                            'value' => $department->follow_master
                                        ],
                                        "image"         => [
                                            "type"         => "crop-image-full",
                                            "label"        => __("Image"),
                                            "value"        => $department->imageSources(720, 480),
                                            "required"     => false,
                                            'noSaveButton' => true,
                                            "full"         => true
                                        ]
                                    ]
                                ],
                            ]
                        ),
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.product_category.update',
                            'parameters' => [
                                'productCategory' => $department->id
                            ]
                        ],
                    ]
                ]
            ]
        );
    }


    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return ShowDepartment::make()->getBreadcrumbs(
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '('.__('Editing').')'
        );
    }


}
