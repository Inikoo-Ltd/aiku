<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Sep 2023 18:42:14 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Banner\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebEditAuthorisation;
use App\Enums\Web\Banner\BannerStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Banner;
use App\Models\Web\Website;
use Exception;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditBanner extends OrgAction
{
    use WithWebEditAuthorisation;

    public function handle(Banner $banner): Banner
    {
        return $banner;
    }


    public function asController(Organisation $organisation, Shop $shop, Website $website, Banner $banner, ActionRequest $request): Banner
    {
        $this->initialisationFromShop($banner->shop, $request);
        return $this->handle($banner);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, Website $website, Banner $banner, ActionRequest $request): Banner
    {

        $this->initialisationFromFulfilment($fulfilment, $request);
        return $this->handle($banner);
    }


    /**
     * @throws Exception
     */
    public function htmlResponse(Banner $banner, ActionRequest $request): Response
    {
        $sections['properties'] = [
            'label'  => __('Banner properties'),
            'icon'   => 'fal fa-sliders-h',
            'fields' => [
                'name'                 => [
                    'type'     => 'input',
                    'label'    => __('name'),
                    'value'    => $banner->name,
                    'required' => true,
                ],

            ]
        ];


        if ($banner->state == BannerStateEnum::LIVE) {
            $sections['shutdown'] = [
                'label'  => __('Shutdown'),
                'icon'   => 'fal fa-power-off',
                'title'  => '',
                'fields' => [
                    'shutdown_action' => [
                        'type'   => 'action',
                        'action' => [
                            'type'  => 'button',
                            'style' => 'secondary',
                            'icon'  => ['fal', 'fa-power-off'],
                            'label' => __('Shutdown banner'),
                            'route' => [
                                'method' => 'patch',
                                'name'       => 'grp.models.shop.website.banner.shutdown',
                                'parameters' => [
                                    'shop' => $banner->shop_id,
                                    'website' => $banner->website_id,
                                    'banner' => $banner->id,
                                ]
                            ],
                        ],
                    ]
                ]
            ];
        }

        $sections['delete'] = [
            'label'  => __('Delete'),
            'icon'   => 'fal fa-trash-alt',
            'fields' => [
                'name' => [
                    'type'   => 'action',
                    'action' => [
                        'type'  => 'button',
                        'style' => 'delete',
                        'label' => __('delete banner'),
                        'route' => [
                            'method' => 'delete',
                            'name'       => 'grp.models.shop.website.banner.delete',
                            'parameters' => [
                                'shop' => $banner->shop_id,
                                'website' => $banner->website_id,
                                'banner' => $banner->id,
                            ]
                        ],
                    ],
                ]
            ]
        ];

        $currentSection = 'properties';
        if ($request->has('section') and Arr::has($sections, $request->get('section'))) {
            $currentSection = $request->get('section');
        }

        return Inertia::render(
            'EditModel',
            [
                'title'       => __("Edit banner"),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    'title'     => $banner->name,
                    'icon'      => [
                        'tooltip' => __('banner'),
                        'icon'    => 'fal fa-sign'
                    ],
                    'iconRight' => $banner->state->stateIcon()[$banner->state->value],
                    'actions'   => [
                        [
                            'type'  => 'button',
                            'style' => 'exit',
                            'label' => __('Exit edit'),
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ],
                ],
                'formData'    => [
                    'current'   => $currentSection,
                    'blueprint' => $sections,
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.shop.website.banner.update',
                            'parameters' => [
                                'shop' => $banner->shop_id,
                                'website' => $banner->website_id,
                                'banner' => $banner->id,
                            ]
                        ],
                    ]
                ],

            ]
        );
    }


    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return ShowBanner::make()->getBreadcrumbs(
            $routeName,
            $routeParameters,
            suffix: '('.__('editing').')'
        );
    }

    private function getNavigation(?Banner $banner, ActionRequest $request): ?array
    {
        if (!$banner) {
            return null;
        }

        $routeName = $request->route()->getName();

        return match ($routeName) {
            'customer.banners.banners.edit' => [
                'label' => $banner->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => $request->route()->originalParameters()
                ]
            ],
            default => null
        };
    }
}
