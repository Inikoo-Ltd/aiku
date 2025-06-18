<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 May 2023 11:40:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Web\Website\WebsiteTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditWebsite extends OrgAction
{
    use WithWebAuthorisation;

    private Fulfilment|Shop $parent;

    public function handle(Website $website): Website
    {
        return $website;
    }


    public function asController(Organisation $organisation, Shop $shop, Website $website, ActionRequest $request): Website
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($website);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, Website $website, ActionRequest $request): Website
    {
        $this->parent = $fulfilment;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($website);
    }


    /**
     * @throws Exception
     */
    public function htmlResponse(Website $website, ActionRequest $request): Response
    {
        if ($website->shop->type == ShopTypeEnum::FULFILMENT) {
            $args = [
                'updateRoute' => [
                    'name'       => 'grp.models.fulfilment.website.update',
                    'parameters' => [
                        $website->shop->fulfilment->id,
                        $website->id,
                    ]
                ],
            ];
        } else {
            $args = [
                'updateRoute' => [
                    'name'       => 'grp.models.website.update',
                    'parameters' => [
                        [

                            $website->id,
                        ]
                    ]
                ],
            ];
        }

        $blueprints = [];

        $blueprints[] = [
            'label'  => __('ID/domain'),
            'icon'   => 'fa-light fa-id-card',
            'fields' => [
                'code'          => [
                    'type'     => 'input',
                    'label'    => __('code'),
                    'value'    => $website->code,
                    'required' => true,
                ],
                'name'          => [
                    'type'     => 'input',
                    'label'    => __('name'),
                    'value'    => $website->name,
                    'required' => true,
                ],
                'domain'        => [
                    'type'      => 'inputWithAddOn',
                    'label'     => __('domain'),
                    'leftAddOn' => [
                        'label' => 'https://www.'
                    ],
                    'value'     => $website->domain,
                    'required'  => true,
                ],
                'google_tag_id' => [
                    'type'          => 'input',
                    'information'   => __('This only available for Google Tag Manager Container ID'),
                    'label'         => __('GTM container ID'),
                    'value'         => Arr::get($website->settings, "google_tag_id"),
                    'placeholder'   => 'GTM-ABC456GH',
                    'required'      => false,
                ],
                'luigisbox_private_key' => [
                    'information' => __('Private key for API Luigi search'),
                    'type'     => 'purePassword',
                    'label'    => __('Luigi Search Private Key'),
                    'value'    => Arr::get($website->settings, "luigisbox.private_key"),
                    'placeholder' => 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
                    'required' => false,
                ],
                'luigisbox_tracker_id' => [
                    'information' => __('For Luigi search in the header'),
                    'type'     => 'input',
                    'label'    => __('Luigi Search Tracker ID'),
                    'value'    => Arr::get($website->settings, "luigisbox.tracker_id"),
                    'placeholder' => '123456-123456',
                    'required' => false,
                ],
                'luigisbox_script_lbx' => [
                    'information' => __('Script for Luigi search in the header'),
                    'type'     => 'input',
                    'label'    => __('Script LBX Luigi Search'),
                    'value'    => Arr::get($website->settings, "luigisbox.script_lbx"),
                    'placeholder' => '<script async src="https://scripts.luigisbox.com/LBX-xxxxxx.js"></script>',
                    'required' => false,
                ],
                "image"         => [
                    "type"    => "image_crop_square",
                    "label"   => __("logo"),
                    "value"   => $website->imageSources(320, 320),
                    'options' => [
                        "minAspectRatio" => 1,
                        "maxAspectRatio" => 12 / 4,
                    ]
                ],
                "favicon"       => [
                    "information"   => __("Will show on browsers tab icon in size 18x18 pixels."),
                    "type"    => "image_crop_square",
                    "label"   => __("favicon"),
                    "value"   => $website->faviconSources(160, 160),
                    'options' => [
                        'aspectRatio' => 1
                    ]
                ],
            ]
        ];

        if (in_array($website->type, [WebsiteTypeEnum::B2B, WebsiteTypeEnum::DROPSHIPPING])) {
            $blueprints[] = [
                'label'  => __('Registrations'),
                'icon'   => 'fa-light fa-id-card',
                'fields' => [
                    'approval'           => [
                        'type'     => 'toggle',
                        'label'    => __('Registrations Approval'),
                        'value'    => false,
                        'required' => true,
                    ],
                    'registrations_type' => [
                        'type'     => 'radio',
                        'mode'     => 'card',
                        'label'    => __('Registration Type'),
                        'value'    => [
                            'title'       => "type B",
                            'description' => 'This user able to create and delete',
                            'label'       => '17 users left',
                            'value'       => "typeB",
                        ],
                        'required' => true,
                        'options'  => [
                            [
                                'title'       => "type A",
                                'description' => 'This user able to edit',
                                'label'       => '425 users left',
                                'value'       => "typeA",
                            ],
                            [
                                'title'       => "type B",
                                'description' => 'This user able to create and delete',
                                'label'       => '17 users left',
                                'value'       => "typeB",
                            ],
                        ]
                    ],
                    'web_registrations'  => [
                        'type'     => 'webRegistrations',
                        'label'    => __('Web Registration'),
                        'value'    => [
                            [
                                'key'      => 'telephone',
                                'name'     => __('telephone'),
                                'show'     => true,
                                'required' => false,
                            ],
                            [
                                'key'      => 'address',
                                'name'     => __('address'),
                                'show'     => false,
                                'required' => false,
                            ],
                            [
                                'key'      => 'company',
                                'name'     => __('company'),
                                'show'     => false,
                                'required' => false,
                            ],
                            [
                                'key'      => 'contact_name',
                                'name'     => __('contact_name'),
                                'show'     => false,
                                'required' => false,
                            ],
                            [
                                'key'      => 'registration_number',
                                'name'     => __('registration number'),
                                'show'     => true,
                                'required' => false,
                            ],
                            [
                                'key'      => 'tax_number',
                                'name'     => __('tax number'),
                                'show'     => false,
                                'required' => false,
                            ],
                            [
                                'key'      => 'terms_and_conditions',
                                'name'     => __('terms and conditions'),
                                'show'     => true,
                                'required' => true,
                            ],
                            [
                                'key'      => 'marketing',
                                'name'     => __('marketing'),
                                'show'     => false,
                                'required' => false,
                            ],
                        ],
                        'required' => true,
                        'options'  => [
                            [
                                'key'      => 'telephone',
                                'name'     => __('telephone'),
                                'show'     => true,
                                'required' => false,
                            ],
                            [
                                'key'      => 'address',
                                'name'     => __('address'),
                                'show'     => false,
                                'required' => false,
                            ],
                            [
                                'key'      => 'company',
                                'name'     => __('company'),
                                'show'     => false,
                                'required' => false,
                            ],
                            [
                                'key'      => 'contact_name',
                                'name'     => __('contact name'),
                                'show'     => false,
                                'required' => false,
                            ],
                            [
                                'key'      => 'registration_number',
                                'name'     => __('registration number'),
                                'show'     => true,
                                'required' => false,
                            ],
                            [
                                'key'      => 'tax_number',
                                'name'     => __('tax number'),
                                'show'     => false,
                                'required' => false,
                            ],
                            [
                                'key'      => 'terms_and_conditions',
                                'name'     => __('terms and conditions'),
                                'show'     => true,
                                'required' => false,
                            ],
                            [
                                'key'      => 'marketing',
                                'name'     => __('marketing'),
                                'show'     => false,
                                'required' => false,
                            ],
                        ]
                    ]
                ]
            ];
            $blueprints[] = [
                'label'  => __('Return Policy'),
                'icon'   => 'fa-light fa-exchange',
                'fields' => [
                    'return_policy' => [
                        'type'     => 'editor',
                        'label'    => __('Return Policy'),
                        'value'    => Arr::get($website->settings, 'return_policy'),
                        'required' => false,
                    ],
                ]
            ];
        }

        /* $blueprints[] = [
               'label'  => __('Script'),
               'icon'   => 'fa-light fa-code',
               'fields' => [
                   'script_website' => [
                       'type'     => 'editor',
                       'label'    => __('Script'),
                       'value'    => Arr::get($website->settings, 'script_website.header'),
                       'required' => false,
                   ],
               ]
           ]; */


        return Inertia::render(
            'EditModel',
            [
                'title'       => __("Website's settings"),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($website, $request),
                    'next'     => $this->getNext($website, $request),
                ],
                'pageHead'    => [
                    'title'     => __('Settings'),
                    'container' => [
                        'icon'    => ['fal', 'fa-globe'],
                        'tooltip' => __('Website'),
                        'label'   => Str::possessive($website->name)
                    ],

                    'iconRight' =>
                        [
                            'icon'  => ['fal', 'sliders-h'],
                            'title' => __("Website's settings")
                        ],

                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'label' => __('Exit settings'),
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ],
                ],
                'formData'    => [
                    'blueprint' => $blueprints,
                    'args' => $args
                ],

            ]
        );
    }


    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return ShowWebsite::make()->getBreadcrumbs(
            $routeName,
            $routeParameters,
            suffix: '('.__('Editing').')'
        );
    }

    public function getPrevious(Website $website, ActionRequest $request): ?array
    {
        $previous = Website::where('code', '<', $website->code)->orderBy('code', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(Website $website, ActionRequest $request): ?array
    {
        $next = Website::where('code', '>', $website->code)->orderBy('code')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?Website $website, string $routeName): ?array
    {
        if (!$website) {
            return null;
        }

        return match ($routeName) {
            'grp.org.shops.show.web.websites.edit' => [
                'label' => $website->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $website->shop->organisation->slug,
                        'shop'         => $website->shop->slug,
                        'website'      => $website->slug
                    ]
                ]
            ],
            'grp.org.fulfilments.show.web.websites.edit' => [
                'label' => $website->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $this->parent->organisation->slug,
                        'fulfilment'   => $this->parent->slug,
                        'website'      => $website->slug
                    ]
                ]
            ]
        };
    }
}
