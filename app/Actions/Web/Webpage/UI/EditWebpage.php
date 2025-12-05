<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 21 Sep 2023 11:35:41 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Exception;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\LaravelOptions\Options;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;

class EditWebpage extends OrgAction
{
    use WithWebAuthorisation;


    public function handle(Webpage $webpage): Webpage
    {
        return $webpage;
    }


    public function asController(Organisation $organisation, Shop $shop, Website $website, Webpage $webpage, ActionRequest $request): Webpage
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($webpage);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, Website $website, Webpage $webpage, ActionRequest $request): Webpage
    {
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($webpage);
    }

    /**
     * @throws Exception
     */
    public function htmlResponse(Webpage $webpage, ActionRequest $request): Response
    {
        $isBlog = $webpage->type == WebpageTypeEnum::BLOG;


        $fields = [
            "seo_image"        => [
                "type"    => "image_crop_square",
                "label"   => __("share image"),
                "value"   => $webpage->imageSources(1200, 1200, 'seoImage'),
                'options' => [
                    "minAspectRatio" => 1,
                    "maxAspectRatio" => 12 / 4,
                ]
            ],
            'code'             => [
                'type'        => 'input',
                'label'       => __('Code'),
                'information' => __('Use for internal use'),
                'value'       => $webpage->code,
                'required'    => true,
            ],
            'url'              => [
                'type'      => 'inputWithAddOn',
                'label'     => __('URL'),
                'leftAddOn' => [
                    'label' => $isBlog ? 'https://'.$webpage->website->domain.'/blog' : 'https://'.$webpage->website->domain.'/'
                ],
                'value'     => $webpage->url,
                'required'  => true,
            ],
            'breadcrumb_label' => [
                // for now, we're forcing the breadcrumbs to show product code so no need for this
                'hidden'      => $webpage->model_type == 'Product',
                'type'        => 'input',
                'label'       => __('Breadcrumb label').' ('.__('Optional').')',
                'information' => __('To be used for the breadcrumbs, will use Meta Title if missing'),
                'options'     => [
                    'counter' => true,
                ],
                'value'       => $webpage->breadcrumb_label,
            ],
            'title'            => [
                'type'        => 'input',
                'label'       => __('Meta Title').' (& '.__('Browser title').')',
                'information' => __('This will be used for the title seen in the browser, and meta title for SEO'),
                'options'     => [
                    'counter' => true,
                ],
                'value'       => $webpage->title,
            ],
            'description'      => [
                'type'        => 'textarea',
                'label'       => __('Meta Description'),
                'information' => __('This will be used for the meta description'),
                'options'     => [
                    'counter' => true,
                ],
                'value'       => $webpage->description,
                "maxLength"   => 150,
                "counter"     => true,
            ],
        ];


        if ($webpage->model_type == 'Product') {
            /** @var \App\Models\Catalogue\Product $product */
            $product       = $webpage->model;
            $productFields = [
                'product_name'              => [
                    'type'        => 'input',
                    'label'       => __('Product Name'),
                    'information' => __('This will displayed as h1 in the product page on website and in orders and invoices.'),
                    'options'     => [
                        'counter' => true,
                    ],
                    'value'       => $product->name
                ],
                'product_description'       => [
                    'type'        => 'textEditor',
                    'label'       => __('Product Description'),
                    'information' => __('This show in product webpage'),
                    'options'     => [
                        'counter' => true,
                    ],
                    'value'       => $product->description
                ],
                'product_description_extra' => [
                    'type'        => 'textEditor',
                    'label'       => __('Product Extra description'),
                    'information' => __('This above product specification in product webpage'),
                    'options'     => [
                        'counter' => true,
                    ],
                    'value'       => $product->description_extra
                ],
            ];


            $fields = array_merge($fields, $productFields);
        }


        $mainData = [
            'label'  => $isBlog ? __('Blog') : __('Webpage'),
            'icon'   => 'fal fa-browser',
            'fields' => $fields

        ];


        return Inertia::render(
            'EditModel',
            [
                'title'       => $isBlog ? __("Blog's Settings") : __("Webpage's settings"),
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),

                'pageHead' => [
                    'title'      => __('Settings'),
                    'icon'       => [
                        'icon'  => ['fal', 'sliders-h'],
                        'title' => __("Webpage settings")
                    ],
                    'model'      => $isBlog ? __('Blog') : __('Webpage'),
                    'iconRight'  => WebpageStateEnum::stateIcon()[$webpage->state->value],
                    'afterTitle' => [
                        'label' => $webpage->getUrl(),
                    ],

                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exit',
                            'label' => __('Exit settings'),
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ],
                ],
                'formData' => [
                    'blueprint' => [
                        $mainData,
                        [
                            'label'  => __('Structured data'),
                            'icon'   => 'fal fa-brackets-curly',
                            'fields' => [
                                'structured_data' => [
                                    'noTitle'  => true,
                                    'type'     => 'structure_data_website',
                                    'value'    => Arr::get($webpage->seo_data, 'structured_data') ?? '',
                                    'required' => false,
                                ],
                            ]
                        ],
                        [
                            'label'  => __('Set online/closed'),
                            'icon'   => 'fal fa-broadcast-tower',
                            'fields' => [
                                'state_data' => [
                                    'type'               => 'toggle_state_webpage',
                                    'label'              => __('State'),
                                    'placeholder'        => __('Select webpage state'),
                                    'required'           => true,
                                    'options'            => Options::forEnum(WebpageStateEnum::class),
                                    'searchable'         => true,
                                    'default_storefront' => getFieldWebpageData(Webpage::where('type', WebpageTypeEnum::STOREFRONT)->where('shop_id', $webpage->shop_id)->first()),
                                    'init_options'       => $webpage->redirectWebpage ? [
                                        getFieldWebpageData($webpage->redirectWebpage)
                                    ] : null,
                                    'value'              => [
                                        'state'               => $webpage->state,
                                        'redirect_webpage_id' => $webpage->redirect_webpage_id,
                                    ],
                                ],
                            ]
                        ],
                        [
                            'label'  => __('Delete'),
                            'icon'   => 'fal fa-trash-alt',
                            'fields' => [
                                'name' => [
                                    'type'               => 'delete_webpage',
                                    'noSaveButton'       => true,
                                    'current_state'      => $webpage->state,
                                    'default_storefront' => getFieldWebpageData(Webpage::where('type', WebpageTypeEnum::STOREFRONT)->where('shop_id', $webpage->shop_id)->first()),
                                    'init_options'       => $webpage->redirectWebpage ? [
                                        getFieldWebpageData($webpage->redirectWebpage)
                                    ] : null,
                                    'value'              => [
                                        'state'               => $webpage->state,
                                        'redirect_webpage_id' => $webpage->redirect_webpage_id,
                                    ],
                                    'route_delete'       => [
                                        'method'     => 'patch',
                                        'name'       => 'grp.models.webpage.delete',
                                        'parameters' => [
                                            'webpage' => $webpage->id,
                                        ]
                                    ],
                                ]
                            ]
                        ]
                    ],
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.webpage.update',
                            'parameters' => [
                                'webpage' => $webpage->id
                            ]
                        ],
                    ]
                ],

            ]
        );
    }


    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        if ($routeName == 'grp.org.shops.show.web.blogs.edit') {
            return ShowBlogWebpage::make()->getBreadcrumbs(
                $routeName,
                $routeParameters,
                suffix: '('.__('settings').')'
            );
        }

        return ShowWebpage::make()->getBreadcrumbs(
            $routeName,
            $routeParameters,
            suffix: '('.__('settings').')'
        );
    }
}
