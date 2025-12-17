<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 08:53:02 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\RentalAgreement\UI;

use App\Actions\Fulfilment\FulfilmentCustomer\ShowFulfilmentCustomer;
use App\Actions\OrgAction;
use App\Enums\Fulfilment\RentalAgreement\RentalAgreementBillingCycleEnum;
use App\Enums\Fulfilment\RentalAgreement\RentalAgreementStateEnum;
use App\Http\Resources\Catalogue\ProductsResource;
use App\Http\Resources\Catalogue\RentalsResource;
use App\Http\Resources\Catalogue\ServicesResource;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\RentalAgreement;
use App\Models\SysAdmin\Organisation;
use Exception;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\LaravelOptions\Options;

class EditRentalAgreement extends OrgAction
{
    private function getOrdinal($number): string
    {
        if (is_integer($number)) {
            $suffixes = ['th', 'st', 'nd', 'rd', 'th', 'th', 'th', 'th', 'th', 'th'];
            if ((($number % 100) >= 11) && (($number % 100) <= 13)) {
                return $number . 'th';
            }
            return $number . $suffixes[$number % 10];
        }

        return $number;
    }

    /**
     * @throws Exception
     */
    public function handle(RentalAgreement $rentalAgreement, ActionRequest $request): Response
    {
        $clauses = null;
        foreach ($rentalAgreement->fulfilmentCustomer->rentalAgreementClauses as $clause) {
            $price                                  = $clause->asset->price;
            $percentageOff                          = $clause->percentage_off;
            $discount                               = $percentageOff / 100;
            $clauses[$clause->asset->type->value][] = [
                'asset_id'       => $clause->asset_id,
                'agreed_price'   => $price - $price * $discount,
                'price'          => $price,
                'percentage_off' => $percentageOff
            ];
        }





        $stateOptions = [];
        if ($rentalAgreement->state !== RentalAgreementStateEnum::ACTIVE) {
            $stateOptions = [
                'state' => [
                    'type'     => 'radio',
                    'label'    => __('state'),
                    'mode'     => "tabs",
                    'valueProp' => 'value',
                    'required' => true,
                    'value'    => $rentalAgreement->state,
                    'options'  => [
                        [
                            "label" => "Draft",
                            "value" => "draft"
                        ],
                        [
                            "label" => "Active",
                            "value" => "active"
                        ],
                    ],
                ],
            ];
        }

        $billing_weekdays_only_text =  Arr::get($this->fulfilment->settings, 'rental_agreement_cut_off.monthly.workdays')
            ? ' (' . __('Weekdays only') . ')'
            : null;


        return Inertia::render(
            'EditModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'title'       => __('Edit rental agreement'),
                'pageHead'    => [
                    'title'     => __('Edit rental agreement'),
                    'actions'   => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => 'grp.org.fulfilments.show.crm.customers.show',
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ],
                ],
                'formData'    => [
                    'fullLayout' => true,
                    'blueprint'  =>
                        [
                            [
                                'title'  => '',
                                'fields' => [
                                    'billing_cycle' => [
                                        'type'     => 'select_billing_cycle',
                                        'label'    => __('billing cycle'),
                                        'required' => true,
                                        'additional_description' => [
                                            'description' => [
                                                RentalAgreementBillingCycleEnum::MONTHLY->value => __('Billing cycle is on') . ' ' . $this->getOrdinal($this->fulfilment->settings['rental_agreement_cut_off']['monthly']['day']) . ' ' . __('of each month') . $billing_weekdays_only_text . ('.'),
                                                RentalAgreementBillingCycleEnum::WEEKLY->value => __('Billing cycle is on') . ' ' .  $this->fulfilment->settings['rental_agreement_cut_off']['weekly']['day'] . ' ' . __('of each week') . ('.'),
                                            ],
                                            'route' => [
                                                'name'       => 'grp.org.fulfilments.show.settings.edit',
                                                'parameters' => [
                                                    'organisation' => $this->organisation->slug,
                                                    'fulfilment'   => $this->fulfilment->slug,
                                                    'section'      => '4'

                                                ]
                                            ]
                                        ],
                                        'options'  => Options::forEnum(RentalAgreementBillingCycleEnum::class),
                                        'value'    => $rentalAgreement->billing_cycle
                                    ],
                                    'pallets_limit' => [
                                        'type'        => 'input',
                                        'label'       => __('pallets limit'),
                                        'placeholder' => '0',
                                        'required'    => false,
                                        'value'       => $rentalAgreement->pallets_limit
                                    ],
                                    ...$stateOptions,

                                    'clauses'       => [
                                        'type'           => 'rental',
                                        'label'          => __('Clauses'),
                                        'required'       => false,
                                        'full'           => true,
                                        'noSaveButton'   => true,
                                        'rentals'        => RentalsResource::collection($rentalAgreement->fulfilment->rentals),
                                        'services'       => ServicesResource::collection($rentalAgreement->fulfilment->shop->services),
                                        'physical_goods' => ProductsResource::collection($rentalAgreement->fulfilment->shop->products),
                                        'value'          => $clauses
                                    ]


                                ]
                            ]
                        ],
                    'args'       => [
                        'updateRoute' => [
                            'name'       => 'grp.models.rental-agreement.update',
                            'parameters' => [
                                'rentalAgreement'    => $rentalAgreement->id,
                            ]
                        ],
                    ]
                ],
            ]
        );
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.edit");
    }

    /**
     * @throws Exception
     */
    public function asController(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): Response
    {
        $rentalAgreement = $fulfilmentCustomer->rentalAgreement;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($rentalAgreement, $request);
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowFulfilmentCustomer::make()->getBreadcrumbs(
                routeParameters: $routeParameters,
            ),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating rental agreement'),
                    ]
                ]
            ]
        );
    }

}
