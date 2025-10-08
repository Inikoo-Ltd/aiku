<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:32:25 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Prospect\UI;

use App\Actions\Helpers\Country\UI\GetAddressData;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Http\Resources\Helpers\AddressFormFieldsResource;
use App\Http\Resources\Helpers\TaxNumberResource;
use App\Models\CRM\Customer;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Prospect;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditProspect extends OrgAction
{
    public function handle(Prospect $prospect): Prospect
    {
        return $prospect;
    }



    public function asController(Organisation $organisation, Shop $shop, Prospect $prospect, ActionRequest $request): Prospect
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($prospect);
    }

    public function htmlResponse(Prospect $prospect, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'title'       => __('prospect'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                // 'navigation'  => [
                //     'previous' => $this->getPrevious($prospect, $request),
                //     'next'     => $this->getNext($prospect, $request),
                // ],
                'pageHead'    => [
                    'title'   => $prospect->name,
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters()),
                            ]
                        ]
                    ],
                ],


                'formData' => [
                    'blueprint' => [
                        [
                            'title'  => __('contact information'),
                            'label'  => __('contact'),
                            'fields' => [
                                'contact_name'             => [
                                    'type'  => 'input',
                                    'label' => __('contact name'),
                                    'value' => $prospect->contact_name
                                ],
                                'company_name'             => [
                                    'type'  => 'input',
                                    'label' => __('company'),
                                    'value' => $prospect->company_name
                                ],
                                'phone'                    => [
                                    'type'  => 'phone',
                                    'label' => __('Phone'),
                                    'value' => $prospect->phone
                                ],
                                'contact_address'          => [
                                    'type'    => 'address',
                                    'label'   => __('Address'),
                                    'value'   => AddressFormFieldsResource::make($prospect->address)->getArray(),
                                    'options' => [
                                        'countriesAddressData' => GetAddressData::run()
                                    ]
                                ],
                            ]
                        ]
                    ],
                    // 'args'      => [
                    //     'updateRoute' => [
                    //         'name'       => 'grp.models.customer.update',
                    //         'parameters' => [$customer->id]

                    //     ],
                    // ]

                ],

            ]
        );
    }


    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return ShowProspect::make()->getBreadcrumbs(
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '('.__('Editing').')'
        );
    }

    // public function getPrevious(Prospect $prospect, ActionRequest $request): ?array
    // {
    //     $previous = Prospect::where('slug', '<', $prospect->slug)->orderBy('slug', 'desc')->first();

    //     return $this->getNavigation($previous, $request->route()->getName());
    // }

    // public function getNext(Prospect $prospect, ActionRequest $request): ?array
    // {
    //     $next = Prospect::where('slug', '>', $prospect->slug)->orderBy('slug')->first();

    //     return $this->getNavigation($next, $request->route()->getName());
    // }

    // private function getNavigation(?Prospect $prospect, string $routeName): ?array
    // {
    //     if (!$prospect) {
    //         return null;
    //     }

    //     return match ($routeName) {
    //         'grp.org.shops.show.crm.customers.edit' => [
    //             'label' => $customer->name,
    //             'route' => [
    //                 'name'       => $routeName,
    //                 'parameters' => [
    //                     'organisation' => $customer->organisation->slug,
    //                     'shop'         => $customer->shop->slug,
    //                     'customer'     => $customer->slug
    //                 ]

    //             ]
    //         ]
    //     };
    // }
}
