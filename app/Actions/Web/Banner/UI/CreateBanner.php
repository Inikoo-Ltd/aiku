<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Sep 2023 18:42:14 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Banner\UI;

use App\Actions\OrgAction;
use App\Enums\Web\Banner\BannerTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\LaravelOptions\Options;

class CreateBanner extends OrgAction
{
    public function asController(Organisation $organisation, Shop $shop, Website $website, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($website, $request);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, Website $website, ActionRequest $request): Response
    {
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($website, $request);
    }


    public function handle(Website $website, ActionRequest $request): Response
    {


        $fields = [];

        $fields[] = [
            'title'  => '',
            'fields' => [
                'type' => [
                    'type'     => 'radio',
                    'label'    => __('orientation'),
                    'required' => true,
                    'value'    => BannerTypeEnum::LANDSCAPE,
                    'options'  => Options::forEnum(BannerTypeEnum::class)

                ],

                'name' => [
                    'type'        => 'input',
                    'label'       => __('name'),
                    'placeholder' => __('Name for new banner'),
                    'required'    => true,
                    'value'       => '',
                ],
            ]
        ];

        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('New banner'),
                'pageHead'    => [
                    'title'   => __('banner'),
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'route' => [
                                'name'       => preg_replace('/create$/', 'index', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ]
                ],
                'formData'    => [
                    'blueprint' => $fields,
                    'route'     => [
                        'name'       => 'grp.models.website.banner.store',
                        'parameters' => [
                            'website' => $website->id
                        ]
                    ],
                ],
            ]
        );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return match ($routeName) {
            'grp.org.shops.show.web.banners.create' => array_merge(
                IndexBanners::make()->getBreadcrumbs(
                    'grp.org.shops.show.web.banners.index',
                    $routeParameters
                ),
                [
                    [
                        'type'          => 'creatingModel',
                        'creatingModel' => [
                            'label' => __("creating banner"),
                        ]
                    ]
                ]
            ),
            'grp.org.fulfilments.show.web.banners.create' => array_merge(
                IndexBanners::make()->getBreadcrumbs(
                    'grp.org.fulfilments.show.web.banners.index',
                    $routeParameters
                ),
                [
                    [
                        'type'          => 'creatingModel',
                        'creatingModel' => [
                            'label' => __("creating banner"),
                        ]
                    ]
                ]
            )
        };
    }
}
