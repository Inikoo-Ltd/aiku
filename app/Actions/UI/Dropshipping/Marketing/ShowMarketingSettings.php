<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Apr 2026 15:43:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Dropshipping\Marketing;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowMarketingSettings extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("marketing.{$this->shop->id}.view");
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): ActionRequest
    {
        $this->initialisationFromShop($shop, $request);

        return $request;
    }

    public function htmlResponse(ActionRequest $request): Response
    {
        $title = __('Marketing Settings');

        return Inertia::render(
            'EditModel',
            [
                'breadcrumbs'  => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'        => $title,
                'pageHead'     => [
                    'title' => $title,
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'label' => __('Exit edit'),
                            'route' => [
                                'name'       => 'grp.org.shops.show.marketing.dashboard',
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ],
                ],
                'formData' => [
                    'fullLayout' => true,
                    'blueprint' => [
                        [
                            'title'  => __('Tracking Event Settings'),
                            'fields' => [
                                'marketing_days' => [
                                    'type'  => 'input_number',
                                    'label' => __('Tracking Duration (Days)'),
                                    'value' => Arr::get($this->shop->settings, 'marketing.days'),
                                    'information' => __('Defines how many days an mailshot/newsletter will continue to be tracked after it is start sending. default value if empty is 30 days'),
                                ],
                                'marketing_hours' => [
                                    'type'  => 'input_number',
                                    'label' => __('Tracking Interval (Hours)'),
                                    'value' => Arr::get($this->shop->settings, 'marketing.hours'),
                                    'information' => __('Defines how often the system checks for tracking events (e.g., every 2 hours). default value if empty is 3 hours'),
                                ],
                            ]
                        ]
                    ],
                    'args' => [
                        'updateRoute' => [
                            'name'       => 'grp.org.shops.show.marketing.settings.update',
                            'parameters' => [
                                'organisation' => $this->organisation->slug,
                                'shop'         => $this->shop->slug,
                            ],
                        ],
                    ],
                ]
            ]
        );
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return
            array_merge(
                ShowShop::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.shops.show.marketing.dashboard',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Marketing')
                        ]
                    ],
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.shops.show.marketing.settings',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Settings')
                        ]
                    ]
                ]
            );
    }
}
