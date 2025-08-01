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
use App\Enums\Web\Webpage\WebpageSeoStructureTypeEnum;
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

    public function getFieldWebpageData(Webpage $webpage): array
    {
        return [
            'code'     => $webpage->code,
            'id'       => $webpage->id,
            'href'     => 'https://'.$webpage->website->domain.'/'.$webpage->url,
            "typeIcon" => $webpage->type->stateIcon()[$webpage->type->value] ?? ["fal", "fa-browser"],
        ];
    }

    /**
     * @throws Exception
     */
    public function htmlResponse(Webpage $webpage, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'title'       => __("Webpage's settings"),
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),

                'pageHead' => [
                    'title' => __('Settings'),
                    'icon' => [
                        'icon'  => ['fal', 'sliders-h'],
                        'title' => __("Webpage settings")
                    ],
                    'model' => __('Webpage'),
                    'iconRight' => WebpageStateEnum::stateIcon()[$webpage->state->value],
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
                        [
                            'label'  => __('Webpage'),
                            'icon'   => 'fal fa-browser',
                            'fields' => [
                                 "seo_image"         => [
                                    "type"    => "image_crop_square",
                                    "label"   => __("Preview image"),
                                    "value"   => $webpage->imageSources(1200, 1200, 'seoImage'),
                                    'options' => [
                                        "minAspectRatio" => 1,
                                        "maxAspectRatio" => 12 / 4,
                                    ]
                                ],
                                'title'       => [
                                    'type'                => 'input',
                                    'label'               => __('Title'),
                                    'label_no_capitalize' => true,
                                    'value'               => $webpage->title,
                                    'required'            => true,
                                ],
                                 'description'       => [
                                    'type'                => 'textarea',
                                    'label'               => __('Description'),
                                    'label_no_capitalize' => true,
                                    'value'               => $webpage->description,
                                    'required'            => true,
                                    "maxLength"     => 150,
                                    "counter"       => true,
                                ],
                                'allow_fetch' => [
                                    'type'  => 'toggle',
                                    'label' => __('Allow fetch'),
                                    'value' => $webpage->allow_fetch,
                                ],
                            ]
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
                                        "structured_data"      => Arr::get($webpage->seo_data, 'structured_data') ?? '',
                                        "structured_data_type" => Arr::get($webpage->seo_data, 'structured_data_type') ?? '',
                                    ],
                                    'required' => true,
                                ],
                            ]
                        ],
                        [
                            'label'  => __('Set online/closed'),
                            'icon'   => 'fal fa-broadcast-tower',
                            'fields' => [
                                'state_data'       => [
                                    'type'        => 'toggle_state_webpage',
                                    'label'       => __('State'),
                                    'placeholder' => __('Select webpage state'),
                                    'required'    => true,
                                    'options'     => Options::forEnum(WebpageStateEnum::class),
                                    'searchable'  => true,
                                    'default_storefront' => $this->getFieldWebpageData(Webpage::where('type', WebpageTypeEnum::STOREFRONT)->where('shop_id', $webpage->shop_id)->first()),
                                    'init_options'  => $webpage->redirectWebpage ? [
                                        $this->getFieldWebpageData($webpage->redirectWebpage)
                                    ] : null,
                                    'value'       => [
                                        'state'                 => $webpage->state,
                                        'redirect_webpage_id'   => $webpage->redirect_webpage_id,
                                    ],
                                ],
                            ]
                        ],
                        [
                            'label'  => __('Delete'),
                            'icon'   => 'fal fa-trash-alt',
                            'fields' => [
                                'name' => [
                                    'type'                  => 'delete_webpage',
                                    'noSaveButton'          => true,
                                    'current_state'         => $webpage->state,
                                    'default_storefront'    => $this->getFieldWebpageData(Webpage::where('type', WebpageTypeEnum::STOREFRONT)->where('shop_id', $webpage->shop_id)->first()),
                                    'init_options'  => $webpage->redirectWebpage ? [
                                        $this->getFieldWebpageData($webpage->redirectWebpage)
                                    ] : null,
                                    'value'       => [
                                        'state'                 => $webpage->state,
                                        'redirect_webpage_id'   => $webpage->redirect_webpage_id,
                                    ],
                                    'route_delete' => [
                                        'method'     => 'patch',
                                        'name'       => 'grp.models.webpage.delete',
                                        'parameters' => [
                                            // 'shop'    => $webpage->shop->id,
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
        return ShowWebpage::make()->getBreadcrumbs(
            $routeName,
            $routeParameters,
            suffix: '('.__('settings').')'
        );
    }


}
