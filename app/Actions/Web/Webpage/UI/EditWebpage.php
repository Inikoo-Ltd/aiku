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
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
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
        abort_unless($webpage->canBeEditedBy($request->user()), 403, $webpage->lockMessage());

        $isBlog = $webpage->type == WebpageTypeEnum::BLOG;
        $isSystemPage = $webpage->type == WebpageTypeEnum::SYSTEM_PAGE;
        $isBlogDashboardPage = $isSystemPage && $webpage->sub_type == WebpageSubTypeEnum::BLOG_DASHBOARD_PAGE;

        $fields = [
            "seo_image"        => [
                "type"        => "image_crop_square",
                "label"       => __("Share image"),
                "value"       => $webpage->seo_image_url
                    ? ['original' => $webpage->seo_image_url]
                    : $webpage->imageSources(1200, 1200, 'seoImage'),
                "information" => __("The preview image (og:image) shown when the page is shared on social media (i.e Whatsapp, Facebook). It is not shown on the page itself. Crop ratio from 1:1 to 3:1, served scaled down to fit 1200x1200 pixels."),
                'hasOther'    => [
                    [
                        'name'        => 'seo_image_alt',
                        'type'        => 'alt',
                        'value'       => Arr::get($webpage->seo_data, 'image_alt'),
                        'label'       => __('Share image alt text'),
                        'placeholder' => __('Describe the image'),
                        'information' => __('Alternative text of the share image, used by screen readers and shown when the image cannot be loaded. Will use the Meta Title if missing.'),
                    ],
                    [
                        'name'        => 'seo_image_url',
                        'type'        => 'url',
                        'value'       => $webpage->seo_image_url,
                        'label'       => __('Or paste an image link'),
                        'placeholder' => 'https://',
                        'information' => __('Use an externally hosted image as the share image instead of uploading one. It takes precedence over the uploaded image; uploading a new image clears it.'),
                    ],
                ],
                'options'     => [
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
                'information' => __('This will be used as the title displayed in the browser, meta title for SEO, and the search feature'),
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
            'use_title_prefix_suffix' => [
                'type'        => 'toggle',
                'label'       => __('Add extra words to the title'),
                'information' => __('Adds your prefix and suffix around this page title, so it shows like "Wholesale Bath Bombs | Ancient Wisdom" in the browser tab and on Google. Switch it off if you want the title to show exactly as you typed it.'),
                'value'       => (bool) Arr::get($webpage->seo_data, 'use_title_prefix_suffix', true),
            ],
            'webpage_title_prefix'  => [
                'type'          => 'input',
                'information'   => __('Words that go in front of this page title. Leave it empty and we will use the one from your website settings.'),
                'label'         => __('Title Prefix'),
                'value'         => data_get($webpage->settings, 'webpage.title_prefix', null),
            ],
            'webpage_title_suffix'  => [
                'type'          => 'input',
                'information'   => __('Words that go after this page title, like your shop name. Leave it empty and we will use the one from your website settings.'),
                'label'         => __('Title Suffix'),
                'value'         => data_get($webpage->settings, 'webpage.title_suffix', null),
            ],
            'show_price'  => [
                'type'          => 'toggle',
                'information'   => __('Toggle whether or not the price is shown when logged out on the webpage. This would not override individual webpage setting (if exists)'),
                'label'         => __('Show Price on Webpage'),
                'value'         => data_get($webpage->settings, 'webpage.show_price', false),
            ],
        ];

        $isHiddenFromSearchEngines = (bool) $webpage->sub_type?->isHiddenFromSearchEngines();

        if (!$isHiddenFromSearchEngines) {
            $fields['index_page'] = [
                'type'        => 'toggle',
                'label'       => __('Index Page'),
                'information' => __('This will be used to determine if the page should be indexed by search engines'),
                'value'       => $webpage->index_page ?? true,
            ];

            $fields['follow_link'] = [
                'type'        => 'toggle',
                'label'       => __('Follow Link'),
                'information' => __('This will be used to determine if the page should be followed by search engines'),
                'value'       => $webpage->follow_link ?? true,
            ];
        }

        if ($isBlog && $webpage->sub_type != WebpageSubTypeEnum::MAILSHOT) {
            $fields['sub_type'] = [
                'type'        => 'select',
                'label'       => __('Blog Category'),
                'placeholder' => __('Select a blog category'),
                'mode'        => 'single',
                'options'     => WebpageSubTypeEnum::blogCategoriesWithLabel($webpage->shop?->type),
                'value'       => $webpage->sub_type?->value ?? '',
                'required'    => true,
            ];
        }

        $inVariant = false;

        if ($webpage->model_type == 'Product') {
            /** @var \App\Models\Catalogue\Product $product */
            $product       = $webpage->model;
            $inVariant = $product->variant_id ? true : false;
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

        if ($isBlogDashboardPage) {
            $fields = Arr::only($fields, ['seo_image', 'title', 'description']);
        }

        $mainData = $webpage->state !== WebpageStateEnum::CLOSED ? [
            'label'  => $isBlog ? __('Blog') : __('Webpage'),
            'icon'   => 'fal fa-browser',
            'fields' => $fields
        ] : null;

        $warning = [];

        if ($inVariant) {
            $warning = [
                'type'  => 'warning',
                'title' => __('Important'),
                'text'  => __('This product is set as a part of a variant. Therefore editing this webpage state is disabled'),
                'icon'  => ['fas', 'fa-exclamation-triangle']
            ];
        } elseif ($isHiddenFromSearchEngines) {
            $warning = [
                'type'  => 'warning',
                'title' => __('Hidden from search engines'),
                'text'  => __('This page is always served as noindex, nofollow and is left out of the sitemap, so the indexing settings are not offered.'),
                'icon'  => ['fas', 'fa-exclamation-triangle']
            ];
        }


        $informationWarning = [];
        if ($webpage->sub_type == WebpageSubTypeEnum::FAMILY) {
            $informationWarning = [
                [
                    'description' => "Structure @type 'ProductGroup' or @type 'Product' inside @graph will have no effect, as it will be overwritten by the system.",
                ]
            ];
        } elseif ($webpage->sub_type == WebpageSubTypeEnum::PRODUCT) {
            $informationWarning = [
                [
                    'description' => "Structure @type 'Product' inside @graph will have no effect, as it will be overwritten by the system.",
                ]
            ];
        }

        return Inertia::render(
            'EditModel',
            [
                'title'       => $isBlog ? __("Blog's Settings") : __("Webpage :webpageCode settings", ['webpageCode' => $webpage->code]),
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'warning'     => $warning,
                'pageHead'    => [
                    'title'      => __('Settings'),
                    'icon'       => [
                        'icon'  => ['fal', 'sliders-h'],
                        'title' => __("Webpage settings")
                    ],
                    'model'      => $isBlog ? __('Blog') : __('Webpage'),
                    'iconRight'  => WebpageStateEnum::stateIcon()[$webpage->state->value],
                    'afterTitle' => [
                        'label' => $webpage->getCanonicalUrl(),
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
                    'blueprint' => array_values(array_filter([
                        ($isSystemPage && !$isBlogDashboardPage) ? null : $mainData,
                        ($webpage->state == WebpageStateEnum::CLOSED || ($isSystemPage && !$isBlogDashboardPage)) ? null : [
                            'label'  => __('Structured data'),
                            'icon'   => 'fal fa-brackets-curly',
                            'fields' => [
                                'structured_data' => [
                                    'noTitle'  => true,
                                    'type'     => 'structure_data_website',
                                    'value'    => Arr::get($webpage->seo_data, 'structured_data') ?? '',
                                    'required' => false,
                                    'information_warning'   => $informationWarning,
                                ],
                            ]
                        ],
                        $inVariant ? [] : [
                            'label'  => __('Set online/closed'),
                            'icon'   => 'fal fa-broadcast-tower',
                            'fields' => [
                                'state_data' => $isSystemPage ? [
                                    'type'     => 'toggle_state_system_page',
                                    'label'    => __('State'),
                                    'required' => true,
                                    'value'    => [
                                        'state' => $webpage->state,
                                    ],
                                ] : [
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
                        $webpage->type == WebpageTypeEnum::SYSTEM_PAGE ? null : [
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
                    ])),
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
