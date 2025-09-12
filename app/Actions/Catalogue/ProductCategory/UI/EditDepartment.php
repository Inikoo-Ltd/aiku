<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Apr 2023 16:36:34 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory\UI;

use App\Actions\Helpers\Language\UI\GetLanguagesOptions;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueEditAuthorisation;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditDepartment extends OrgAction
{
    use WithCatalogueEditAuthorisation;

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
        return Inertia::render(
            'EditModel',
            [
                'title'       => __('department'),
                'warning' => $department->masterProductCategory ? [
                    'type'  =>  'warning',
                    'title' =>  'warning',
                    'text'  =>  __('Changing name or description may affect master department.'),
                    'icon'  => ['fas', 'fa-exclamation-triangle']
                ] : null,
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'                            => [
                    'previous' => $this->getPrevious($department, $request),
                    'next'     => $this->getNext($department, $request),
                ],
                'pageHead'    => [
                    'title'    => $department->name,
                    'icon'     =>
                        [
                            'icon'  => ['fal', 'fa-folder-tree'],
                            'title' => __('department')
                        ],
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
                    'blueprint' =>
                    array_filter(
                        [
                            [
                                'label'  => __('Id'),
                                'icon'   => 'fa-light fa-fingerprint',
                                'fields' => [
                                    'code' => [
                                        'type'  => 'input',
                                        'label' => __('code'),
                                        'value' => $department->code
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
                                        'value' =>  $department->name
                                    ],
                                    'description_title' => [
                                        'type'  => 'input',
                                        'label' => __('description title'),
                                        'value' =>  $department->description_title
                                    ],
                                    'description' => [
                                        'type'  => 'textEditor',
                                        'label' => __('description'),
                                        'value' => $department->description
                                    ],
                                    'description_extra' => [
                                        'type'  => 'textEditor',
                                        'label' => __('Extra description'),
                                        'value' =>  $department->description_extra
                                    ],
                                ]
                            ],
                            /* !$department->master_product_category_id ? [
                                'label'  => __('Translations'),
                                'icon'   => 'fa-light fa-language',
                                'main' => $department->name,
                                'languages_main' => $department->shop->language->code,
                                'fields' => [
                                    'name_i8n' => [
                                        'type'  => 'input_translation',
                                        'label' => __('translate name'),
                                        'languages' => GetLanguagesOptions::make()->getExtraGroupLanguages($department->shop->extra_languages),
                                        'value' => $department->getTranslations('name_i8n')
                                    ],
                                    'description_title_i8n' => [
                                        'type'  => 'input_translation',
                                        'label' => __('translate description title'),
                                        'languages' => GetLanguagesOptions::make()->getExtraGroupLanguages($department->shop->extra_languages),
                                        'value' => $department->getTranslations('description_title_i8n')
                                    ],
                                    'description_i8n' => [
                                        'type'  => 'textEditor_translation',
                                        'label' => __('translate description'),
                                        'languages' => GetLanguagesOptions::make()->getExtraGroupLanguages($department->shop->extra_languages),
                                        'value' => $department->getTranslations('description_i8n')
                                    ],
                                    'description_extra_i8n' => [
                                        'type'  => 'textEditor_translation',
                                        'label' => __('translate description extra'),
                                        'languages' => GetLanguagesOptions::make()->getExtraGroupLanguages($department->shop->extra_languages),
                                        'value' => $department->getTranslations('description_extra_i8n')
                                    ],
                                ]
                            ] : null, */
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
                                        'value'         => $department->cost_price_ratio,
                                        'min'           => 0
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
                                        "type"    => "crop-image-full",
                                        "label"   => __("Image"),
                                        "value"   => $department->imageSources(720, 480),
                                        "required" => false,
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

    public function getPrevious(ProductCategory $department, ActionRequest $request): ?array
    {
        $previous = ProductCategory::where('code', '<', $department->code)->orderBy('code', 'desc')->first();
        return $this->getNavigation($previous, $request->route()->getName());

    }

    public function getNext(ProductCategory $department, ActionRequest $request): ?array
    {
        $next = ProductCategory::where('code', '>', $department->code)->orderBy('code')->first();
        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?ProductCategory $department, string $routeName): ?array
    {
        if (!$department) {
            return null;
        }
        return match ($routeName) {
            'shops.departments.edit' => [
                'label' => $department->name,
                'route' => [
                    'name'      => $routeName,
                    'parameters' => [
                        'department' => $department->slug
                    ]
                ]
            ],
            'shops.show.departments.edit' => [
                'label' => $department->name,
                'route' => [
                    'name'      => $routeName,
                    'parameters' => [
                        'shop'      => $department->shop->slug,
                        'department' => $department->slug
                    ]
                ]
            ],
            default => []
        };
    }
}
