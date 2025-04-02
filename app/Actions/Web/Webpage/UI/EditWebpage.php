<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 21 Sep 2023 11:35:41 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\HasWebAuthorisation;
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
use App\Enums\Web\Webpage\WebpageSeoStructureTypeEnum;

class EditWebpage extends OrgAction
{
    use HasWebAuthorisation;


    public function handle(Webpage $webpage): Webpage
    {
        return $webpage;
    }


    public function asController(Organisation $organisation, Shop $shop, Website $website, Webpage $webpage, ActionRequest $request): Webpage
    {
        $this->scope = $shop;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($webpage);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, Website $website, Webpage $webpage, ActionRequest $request): Webpage
    {
        $this->scope = $fulfilment;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($webpage);
    }

    /**
     * @throws Exception
     */
    public function htmlResponse(Webpage $webpage, ActionRequest $request): Response
    {
        // dump($webpage->toArray());
        $redirectUrlArr = Arr::pluck($webpage->website->redirects->toArray(), 'redirect');
        return Inertia::render(
            'EditModel',
            [
                'title'       => __("Webpage's settings"),
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),

                'pageHead' => [
                    'title' => __('webpage settings'),


                    'iconRight' =>
                        [
                            'icon'  => ['fal', 'sliders-h'],
                            'title' => __("Webpage settings")
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
                        [
                            'label'  => __('Webpage'),
                            'icon'   => 'fal fa-browser',
                            'fields' => [
                                'title' => [
                                    'type'      => 'input',
                                    'label'     => __('Title'),
                                    'label_no_capitalize' => true,
                                    'value'     => $webpage->title,
                                    'required'  => true,
                                ],
                                /* 'url' => [
                                    'type'      => 'inputWithAddOn',
                                    'label'     => __('URL'),
                                    'label_no_capitalize' => true,
                                    'leftAddOn' => [
                                        'label' => 'https://' . (($webpage instanceof Webpage && $webpage->website) ? $webpage->website->domain : '') . '/'
                                    ],
                                    'value'     => $webpage->url,
                                    'required'  => true,

                                ],     */
                            ]
                        ],
                        [
                            'label'  => __('SEO (Settings)'),
                            'icon'   => 'fab fa-google',
                            'fields' => [
                                'google_search' => [
                                    'type'     => 'googleSearch',
                                    'domain'    => $webpage->website->domain . '/',
                                    'value'    => [
                                        'image'         => [    // TODO
                                            'original'  => 'https://socialsharepreview.com/api/image-proxy?url=https%3A%2F%2Fwww.zelolab.com%2Fwp-content%2Fuploads%2F2022%2F12%2Fhow-to-create-and-set-up-a-social-share-preview-image-on-your-website.jpg',
                                        ],
                                        'meta_title'       => Arr::get($webpage->seo_data, 'meta_title')       ?? '',
                                        'meta_description' => Arr::get($webpage->seo_data, 'meta_description') ?? '',
                                        'url'             =>  $webpage->url,
                                        'is_use_canonical_url' => $webpage->is_use_canonical_url,
                                        'canonical_url'     => $webpage->canonical_url,
                                    ],
                                    'noTitle'  => true,
                                ] */
                                'meta_title' => [
                                        'type'     => 'input',
                                        'label'    => __('Meta title'),
                                        'value'    => Arr::get($webpage->seo_data, 'meta_title')
                                ],
                                'meta_description' => [
                                        'type'     => 'textarea',
                                        'label'    => __('Meta description'),
                                        'value'    => Arr::get($webpage->seo_data, 'meta_description')
                                ],
                                /* 'meta_keywords' => [
                                        'type'     => 'textarea',
                                        'label'    => __('Meta keywords'),
                                        'value'    => Arr::get($webpage->seo_data, 'meta_keywords')
                                ], */
                            ],
                        ],
                        [
                            'label'  => __('Structured data'),
                            'icon'   => 'fal fa-brackets-curly',
                            'fields' => [
                                'webpage_type' => [
                                    'noTitle'  => true,
                                    'type'     => 'structure_data_website',
                                        'options'  => Options::forEnum(WebpageSeoStructureTypeEnum::class),
                                        'value'    => [
                                            "structured_data" =>   Arr::get($webpage->seo_data, 'structured_data') ?? '',
                                            "structured_data_type" =>  Arr::get($webpage->seo_data, 'structured_data_type') ?? '',
                                        ],
                                        'required' => true,
                                ],
                            ]
                        ],
                    ],
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.shop.webpage.update',
                            'parameters' => [
                                'shop'    => $webpage->website->shop->id,
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
        return ShowWebpage::make()->getBreadcrumbs(
            $routeName,
            $routeParameters,
            suffix: '('.__('settings').')'
        );
    }


}
