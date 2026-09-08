<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 20 Nov 2024 15:21:28 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Billables\Charge\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\Catalogue\Charge\ChargeStateEnum;
use App\Enums\Catalogue\Charge\ChargeTypeEnum;
use App\Models\Billables\Charge;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\LaravelOptions\Options;

class EditCharge extends OrgAction
{
    use WithCatalogueAuthorisation;

    public function handle(Charge $charge): Charge
    {
        return $charge;
    }

    public function asController(Organisation $organisation, Shop $shop, Charge $charge, ActionRequest $request): Charge
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($charge);
    }


    public function htmlResponse(Charge $charge, ActionRequest $request): Response
    {
        $pricing = null;

        if (!in_array($charge->type, [
            ChargeTypeEnum::OTHER,
            ChargeTypeEnum::MARKETPLACE_FEE,
            ChargeTypeEnum::OTHER->value,
            ChargeTypeEnum::MARKETPLACE_FEE->value
        ])) {
            $rules = Arr::get($charge->settings, 'rules');

            $minOrder = null;
            if ($rules && preg_match('/\d+/', $rules, $matches)) {
                $minOrder = (int)$matches[0];
            }

            $fields = [];
            if (in_array($charge->type, [ChargeTypeEnum::HANGING, ChargeTypeEnum::HANGING->value])) {
                $fields['min_order'] = [
                    'type'        => 'input_number',
                    'information' => __('The charge is applied to orders below this amount'),
                    'label'       => __('Min order'),
                    'value'       => $minOrder
                ];
            }
            $fields['amount'] = [
                'type'  => 'input_number',
                'label' => __('Amount'),
                'value' => Arr::get($charge->settings, 'amount')
            ];

            $pricing = [
                'label'  => __('Pricing'),
                'title'  => __('Edit charge'),
                'fields' => $fields
            ];
        }


        $bluePrints =  [
            [
                'label'  => __('Properties'),
                'title'  => __('Edit charge'),
                'fields' => [
                    'name'        => [
                        'type'        => 'input',
                        'information' => __("Charge name for internal use. But will be displayed to user if the label is empty"),
                        'label'       => __('Name'),
                        'value'       => $charge->name
                    ],
                    'label'       => [
                        'type'        => 'input',
                        'information' => __("This will show in customer's order"),
                        'label'       => __('Label'),
                        'value'       => $charge->label
                    ],
                    'description' => [
                        'type'        => 'textarea',
                        'information' => __("This will show in customer's order as a tooltip"),
                        'label'       => __('Description'),
                        'value'       => $charge->description
                    ],
                    'state'       => [
                        'type'        => 'select',
                        'information' => __('The charge is only applied to orders while it is active'),
                        'label'       => __('State'),
                        'value'       => $charge->state->value,
                        'options'     => Options::forEnum(ChargeStateEnum::class),
                    ],
                ],
            ]
        ];
        if ($pricing) {
            $bluePrints[] = $pricing;
        }


        return Inertia::render(
            'EditModel',
            [
                'title'       => __('Charge'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $charge,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($charge, $request),
                    'next'     => $this->getNext($charge, $request),
                ],
                'pageHead'    => [
                    'title'   => $charge->name,
                    'icon'    => [
                        'title' => __('Charge'),
                        'icon'  => 'fal fa-charging-station'
                    ],
                    'actions' => [
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
                    'blueprint' => $bluePrints,

                    'args' => [
                        'updateRoute' => [
                            'name'       => 'grp.models.charge.update',
                            'parameters' => [
                                'charge' => $charge->id
                            ]

                        ],
                    ]
                ]
            ]
        );
    }

    public function getBreadcrumbs(Charge $charge, string $routeName, array $routeParameters): array
    {
        return ShowCharge::make()->getBreadcrumbs(
            charge: $charge,
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '('.__('Editing').')'
        );
    }

    public function getPrevious(Charge $charge, ActionRequest $request): ?array
    {
        $previous = Charge::where('slug', '<', $charge->slug)->orderBy('slug', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(Charge $charge, ActionRequest $request): ?array
    {
        $next = Charge::where('slug', '>', $charge->slug)->orderBy('slug')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }


    private function getNavigation(?Charge $charge, string $routeName): ?array
    {
        if (!$charge) {
            return null;
        }


        return match ($routeName) {
            'grp.org.shops.show.billables.charges.edit' => [
                'label' => $charge->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $charge->organisation->slug,
                        'shop'         => $charge->shop->slug,
                        'charge'       => $charge->slug
                    ]
                ]
            ],
        };
    }
}
