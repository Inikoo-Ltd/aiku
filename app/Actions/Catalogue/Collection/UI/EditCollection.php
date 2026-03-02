<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Mon, 13 Mar 2023 15:05:41 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Catalogue\Collection\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Http\Resources\Helpers\LanguageResource;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditCollection extends OrgAction
{
    use WithCatalogueAuthorisation;

    private Organisation|Shop|ProductCategory $parent;

    public function handle(Collection $collection): Collection
    {
        return $collection;
    }

    public function asController(Organisation $organisation, Shop $shop, Collection $collection, ActionRequest $request): Collection
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($collection);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inDepartment(Organisation $organisation, Shop $shop, ProductCategory $department, Collection $collection, ActionRequest $request): Collection
    {
        $this->parent = $department;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($collection);
    }


    /** @noinspection PhpUnusedParameterInspection */
    public function inSubDepartment(Organisation $organisation, Shop $shop, ProductCategory $department, ProductCategory $subDepartment, Collection $collection, ActionRequest $request): Collection
    {
        $this->parent = $subDepartment;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($collection);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inSubDepartmentInShop(Organisation $organisation, Shop $shop, ProductCategory $subDepartment, Collection $collection, ActionRequest $request): Collection
    {
        $this->parent = $subDepartment;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($collection);
    }

    public function htmlResponse(Collection $collection, ActionRequest $request): Response
    {
        $shop = $collection->shop;
        $warning = [];
        if (data_get($shop->settings, 'catalog.collection_follow_master', false)) {
            $warning = [
                'warning'     => [
                    'type'  => 'warning',
                    'title' => 'Warning',
                    // 'text'  => __('Changing name or description may affect master family.'), // Isn't true anymore. Not neccessarily the case. Turned off
                    'text'  => __('This shop has enabled the Collections force follow master setting. Updates made on master will overwrite local changes'),
                    'icon'  => ['fas', 'fa-exclamation-triangle'],
                ]
            ];
        }

        $languages = [$collection->shop->language_id => LanguageResource::make($collection->shop->language)->resolve()];
        $nameFields = [
            'name'              => $collection->masterCollection
                ? [
                    'type'          => 'input_translation',
                    'label'         => __('Name'),
                    'language_from' => 'en',
                    'full'          => true,
                    'main'          => $collection->masterCollection->name,
                    'languages'     => $languages,
                    'mode'          => 'single',
                    'value'         => $collection->name,
                    'reviewed'      => $collection->is_name_reviewed,
                    'information'   => __('This will displayed as H1 in the product page on website and in orders and invoices.'),
                ]
                : [
                    'type'        => 'input',
                    'label'       => __('Name'),
                    'information' => __('This will displayed as H1 in the product page on website and in orders and invoices.'),
                    'options'     => [
                        'counter' => true,
                    ],
                    'value'       => $collection->name
                ],
            'description'       => $collection->masterCollection
                ? [
                    'type'          => 'textEditor_translation',
                    'label'         => __('Description'),
                    'language_from' => 'en',
                    'full'          => true,
                    'main'          => $collection->masterCollection->description,
                    'languages'     => $languages,
                    'mode'          => 'single',
                    'value'         => $collection->description,
                    'reviewed'      => $collection->is_description_reviewed,
                    'information'   => __('This show in product webpage'),
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
                    'type'        => 'textEditor',
                    'label'       => __('Description'),
                    'information' => __('This show in product webpage'),
                    'options'     => [
                        'counter' => true,
                    ],
                    'toogle'      => [
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
                    'value'       => $collection->description
                ],
            'description_extra' => $collection->masterCollection
                ? [
                    'type'          => 'textEditor_translation',
                    'label'         => __('Extra description'),
                    'language_from' => 'en',
                    'full'          => true,
                    'main'          => $collection->masterCollection->description_extra,
                    'languages'     => $languages,
                    'mode'          => 'single',
                    'value'         => $collection->description_extra,
                    'reviewed'      => $collection->is_description_extra_reviewed,
                    'information'   => __('This above product specification in product webpage'),
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
                    'type'        => 'textEditor',
                    'label'       => __('Extra description'),
                    'information' => __('This above product specification in product webpage'),
                    'options'     => [
                        'counter' => true,
                    ],
                    'value'       => $collection->description_extra,
                    'toogle'      => [
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
        ];

        return Inertia::render(
            'EditModel',
            [
                'title'       => __('collections'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $this->parent,
                    $collection,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                ...$warning,
                'pageHead'    => [
                    'title'   => $collection->code,
                    'model'   => __('Edit collections'),
                    'icon'    => 'fal fa-cube',
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ]
                    ]
                ],
                'formData'    => [
                    'blueprint' => [
                        [
                            'label'  => __('Name/Description'),
                            'icon'   => 'fa-light fa-tag',
                            'fields' => array_merge([
                                // 'code' => [
                                //     'type'  => 'input',
                                //     'label' => __('Code'),
                                //     'value' => $collection->code
                                // ],
                            ], $nameFields)
                        ],
                        // [
                        //     'label'  => __('Image'),
                        //     'icon'   => 'fa-light fa-image',
                        //     'fields' => [
                        //         "image"       => [
                        //             "type"    => "crop-image-full",
                        //             "label" => __("Main image"),
                        //             "value" => $collection->imageSources(720, 480),
                        //             "required" => false,
                        //             'noSaveButton' => true,
                        //             "full"         => true
                        //         ],
                        //     ]
                        // ]
                    ],
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.collection.update',
                            'parameters' => [
                                'collection'   => $collection->id
                            ]

                        ],
                    ]
                ]

            ]
        );
    }

    public function getBreadcrumbs(Organisation|Shop|ProductCategory $parent, Collection $collection, string $routeName, array $routeParameters): array
    {
        return ShowCollection::make()->getBreadcrumbs(
            $parent,
            $collection,
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '('.__('Editing').')'
        );
    }
}
