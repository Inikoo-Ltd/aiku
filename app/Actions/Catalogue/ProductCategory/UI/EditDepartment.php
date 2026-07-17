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
                'name'       => 'grp.majordomo.redirect_master_product_category',
                'parameters' => [
                    $department->masterProductCategory->id
                ]
            ];
        }
        $languages = [$department->shop->language_id => LanguageResource::make($department->shop->language)->resolve()];

        $warning                     = [];
        $forceFollowMasterDepartment = data_get($department->shop->settings, 'catalog.department_follow_master');

        if ($department->masterProductCategory && $forceFollowMasterDepartment) {
            $warning = [
                'warning' => [
                    'type'  => 'warning',
                    'title' => 'Warning',
                    'text'  => __('This shop has enabled the Department force follow master setting. Updates made on master will overwrite local changes'),
                    'icon'  => ['fas', 'fa-exclamation-triangle'],
                ]
            ];
        }

        $webOptions = $department->webpages->mapWithKeys(fn ($item) => [
            $item->id => [
                'label' => $item->slug,
                'id'    => $item->id
            ]
        ]);

        return Inertia::render(
            'EditModel',
            [
                'title'       => __('Department'),
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
                'formData'    => [
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
                                'label'  => __('FAQ'),
                                'icon'   => 'fa-light fa-question-circle',
                                'fields' => [
                                    'faq' => $department->masterProductCategory
                                        ? [
                                            'type'          => 'faq-shop',
                                            'label'         => __('FAQ'),
                                            'language_from' => 'en',
                                            'full'          => true,
                                            'noSaveButton'  => true,
                                            'main'          => $department->masterProductCategory->faq,
                                            'languages'     => $languages,
                                            'mode'          => 'single',
                                            'value'         => $department->faq,
                                            'routeGetInternalLink' => [
                                                'name' => 'grp.org.shops.show.web.webpages.index',
                                                'parameters' => [
                                                    'shop' => $department->shop->slug,
                                                    'organisation' => $department->organisation->slug,
                                                    'website' => $department->shop->website?->slug
                                                ]
                                            ],
                                            'toogle'        => [
                                               'bold', 'italic', 'underline', 'bulletList','customLink', 'undo', 'redo', 'highlight', 'color', 'clear'
                                            ],
                                        ]
                                        : [
                                            'type'  => 'faq-shop',
                                            'label' => __('FAQ'),
                                            'value' => $department->faq,
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
                                                'routeGetInternalLink' => [
                                                    'name' => 'grp.org.shops.show.web.webpages.index',
                                                    'parameters' => [
                                                        'shop' => $department->shop->slug,
                                                        'organisation' => $department->organisation->slug,
                                                        'website' => $department->shop->website?->slug
                                                    ]
                                                ],
                                                'toogle'        => [
                                                    'heading2',
                                                    'heading3',
                                                    'fontSize',
                                                    'bold',
                                                    'italic',
                                                    'underline',
                                                    'bulletList',
                                                    "fontFamily",
                                                    'orderedList',
                                                    'blockquote',
                                                    'divider',
                                                    'alignLeft',
                                                    'alignRight',
                                                    "customLink",
                                                    'alignCenter',
                                                    'undo',
                                                    'redo',
                                                    'highlight',
                                                    'color',
                                                    'clear'
                                                ],
                                            ]
                                            : [
                                                'type'    => 'textEditor',
                                                'label'   => __('Description'),
                                                'value'   => $department->description,
                                                'options' => [
                                                    'counter' => true,
                                                ],
                                               'routeGetInternalLink' => [
                                                    'name' => 'grp.org.shops.show.web.webpages.index',
                                                    'parameters' => [
                                                        'shop' => $department->shop->slug,
                                                        'organisation' => $department->organisation->slug,
                                                        'website' => $department->shop->website?->slug
                                                    ]
                                                ],
                                                'toogle'  => [
                                                    'heading2',
                                                    'heading3',
                                                    'fontSize',
                                                    'bold',
                                                    'italic',
                                                    'underline',
                                                    'bulletList',
                                                    "fontFamily",
                                                    'orderedList',
                                                    'blockquote',
                                                    'divider',
                                                    'alignLeft',
                                                    'alignRight',
                                                    "customLink",
                                                    'alignCenter',
                                                    'undo',
                                                    'redo',
                                                    'highlight',
                                                    'color',
                                                    'clear'
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
                                                'routeGetInternalLink' => [
                                                    'name' => 'grp.org.shops.show.web.webpages.index',
                                                    'parameters' => [
                                                        'shop' => $department->shop->slug,
                                                        'organisation' => $department->organisation->slug,
                                                        'website' => $department->shop->website->slug
                                                    ]
                                                ],
                                                'toogle'        => [
                                                    'heading2',
                                                    'heading3',
                                                    'fontSize',
                                                    'bold',
                                                    'italic',
                                                    'underline',
                                                    'bulletList',
                                                    "fontFamily",
                                                    'orderedList',
                                                    'blockquote',
                                                    'divider',
                                                    'alignLeft',
                                                    'alignRight',
                                                    "customLink",
                                                    'alignCenter',
                                                    'undo',
                                                    'redo',
                                                    'highlight',
                                                    'color',
                                                    'clear'
                                                ]
                                            ]
                                            : [
                                                'type'    => 'textEditor',
                                                'label'   => __('Extra description'),
                                                'value'   => $department->description_extra,
                                                'options' => [
                                                    'counter' => true,
                                                ],
                                                'routeGetInternalLink' => [
                                                    'name' => 'grp.org.shops.show.web.webpages.index',
                                                    'parameters' => [
                                                        'shop' => $department->shop->slug,
                                                        'organisation' => $department->organisation->slug,
                                                        'website' => $department->shop->website?->slug
                                                    ]
                                                ],
                                                'toogle'  => [
                                                    'heading2',
                                                    'heading3',
                                                    'fontSize',
                                                    'bold',
                                                    'italic',
                                                    'underline',
                                                    'bulletList',
                                                    "fontFamily",
                                                    'orderedList',
                                                    'blockquote',
                                                    'divider',
                                                    'alignLeft',
                                                    'alignRight',
                                                    "customLink",
                                                    'alignCenter',
                                                    'undo',
                                                    'redo',
                                                    'highlight',
                                                    'color',
                                                    'clear'
                                                ],
                                            ],
                                        ...$this->seoFields($department),
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
                                $department->webpage ? [
                                    'label'  => __('Webpage Properties'),
                                    'icon'   => 'fa-light fa-browser',
                                    'fields' => [
                                        'set_main_webpage' => [
                                            'type'     => 'select',
                                            'label'    => __('Main Webpage'),
                                            'value'    => $department->webpage->id,
                                            'options'  => $webOptions->toArray(),
                                            'mode'     => 'single',
                                            'required' => true,
                                        ],
                                    ]
                                ] : null,
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


    private function seoFields(ProductCategory $department): array
    {
        $webpage = $department->webpage;

        if (!$webpage) {
            return [];
        }

        return [
            'webpage_title'       => [
                'type'        => 'input',
                'label'       => __('SEO title'),
                'information' => __('Used as the browser title and as the meta title for search engines'),
                'maxLength'   => 70,
                'options'     => [
                    'counter' => true,
                ],
                'value'       => $webpage->title,
            ],
            'webpage_description' => [
                'type'        => 'textarea',
                'label'       => __('Meta description'),
                'information' => __('Used as the meta description for search engines'),
                'maxLength'   => 160,
                'counter'     => true,
                'value'       => $webpage->description,
            ],
            'webpage_url'         => [
                'type'        => 'inputWithAddOn',
                'label'       => __('URL'),
                'information' => __('Changing the URL will redirect the old one to the new one'),
                'leftAddOn'   => [
                    'label' => 'https://'.$webpage->website->domain.'/'
                ],
                'required'    => true,
                'value'       => $webpage->url,
            ],
        ];
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
