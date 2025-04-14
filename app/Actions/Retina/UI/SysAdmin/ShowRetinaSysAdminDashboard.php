<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 Feb 2024 20:59:47 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\UI\SysAdmin;

use App\Actions\Retina\SysAdmin\GetRetinaCustomerAddressManagement;
use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\PalletDelivery\PalletDeliveryStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Http\Resources\Catalogue\RentalAgreementResource;
use Inertia\Inertia;
use Inertia\Response;
use App\Http\Resources\CRM\CustomersResource;
use App\Http\Resources\Fulfilment\FulfilmentCustomerResource;
use App\Models\Fulfilment\FulfilmentCustomer;
use Lorisleiva\Actions\ActionRequest;

class ShowRetinaSysAdminDashboard extends RetinaAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->is_root;
    }

    public function asController(ActionRequest $request): Response
    {
        $this->initialisation($request);
        return $this->handle($request);
    }

    public function handle(ActionRequest $request): Response
    {
        $title = __('Account');

        $recurringBillData = null;

        if ($this->fulfilmentCustomer->currentRecurringBill) {
            $recurringBillData = [
                'label'         => 'Recurring Bills',
                'start_date'    => $this->fulfilmentCustomer->currentRecurringBill->start_date ?? '',
                'end_date'      => $this->fulfilmentCustomer->currentRecurringBill->end_date ?? '',
                'total'         => $this->fulfilmentCustomer->currentRecurringBill->total_amount ?? '',
                'currency_code' => $this->fulfilmentCustomer->currentRecurringBill->currency->code ?? '',
                'status'        => $this->fulfilmentCustomer->currentRecurringBill->status ?? ''
            ];
        }

        return Inertia::render(
            'SysAdmin/RetinaSysAdminDashboard',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => $title,
                'pageHead'    => [
                    'title' => $title,
                    'icon'  => [
                        'icon'  => ['fal', 'fa-users-cog'],
                        'title' => $title
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'create',
                            'label' => __('user'),
                            'route' => [
                                'name'       => 'retina.sysadmin.web-users.create',
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ]
                    ]

                ],
                'customer'     => CustomersResource::make($this->fulfilmentCustomer->customer)->resolve(),
                'fulfilment_customer' => FulfilmentCustomerResource::make($this->fulfilmentCustomer)->getArray(),
                'rental_agreement'    => [
                    'updated_at'  => $this->rentalAgreement->updated_at ?? null,
                    'stats'       => $this->fulfilmentCustomer->rentalAgreement ? RentalAgreementResource::make($this->fulfilmentCustomer->rentalAgreement) : false,
                    'createRoute' => [
                        'name'       => 'grp.org.fulfilments.show.crm.customers.show.rental-agreement.create',
                        'parameters' => array_values($request->route()->originalParameters())
                    ],
                ],
                'status'              => $this->fulfilmentCustomer->customer->status,
                'additional_data'     => $this->fulfilmentCustomer->data,

                'currency_code'  => $this->fulfilmentCustomer->customer->shop->currency->code,
                'balance'        => [
                    'current'             => $this->fulfilmentCustomer->customer->balance,
                    'credit_transactions' => $this->fulfilmentCustomer->customer->stats->number_credit_transactions
                ],
                'recurring_bill' => $recurringBillData,

                'address_management' => GetRetinaCustomerAddressManagement::run(customer:$this->fulfilmentCustomer->customer),

                'stats'       => [
                    'fulfilment_customer' => $this->getFulfilmentCustomerStats($this->fulfilmentCustomer),
                    [
                        'name'  => __('users'),
                        'stat'  => $this->customer->stats->number_current_web_users,
                        'route' => ['name' => 'retina.sysadmin.web-users.index']
                    ],

                ]

            ]
        );
    }


    public function getFulfilmentCustomerStats(FulfilmentCustomer $fulfilmentCustomer): array
    {
        $stats = [];


        $stats['pallets'] = [
            'label'       => __('Pallets'),
            'count'       => $fulfilmentCustomer->number_pallets_status_storing,
            'tooltip'     => __('Pallets in warehouse'),
            'description' => __('in warehouse'),

        ];

        foreach (PalletStateEnum::cases() as $case) {
            $stats['pallets']['state'][$case->value] = [
                'value' => $case->value,
                'icon'  => PalletStateEnum::stateIcon()[$case->value],
                'count' => PalletStateEnum::count($fulfilmentCustomer)[$case->value] ?? 0,
                'label' => PalletStateEnum::labels()[$case->value]
            ];
        }

        $stats['pallet_deliveries'] = [
            'label'       => __('Deliveries'),
            'count'       => $fulfilmentCustomer->number_pallet_deliveries,
            'tooltip'     => __('Total number pallet deliveries'),
            'description' => ''
        ];
        foreach (PalletDeliveryStateEnum::cases() as $case) {
            $stats['pallet_delivery']['cases'][$case->value] = [
                'value' => $case->value,
                'icon'  => PalletDeliveryStateEnum::stateIcon()[$case->value],
                'count' => PalletDeliveryStateEnum::count($fulfilmentCustomer)[$case->value],
                'label' => PalletDeliveryStateEnum::labels()[$case->value]
            ];
        }

        $stats['pallet_returns'] = [
            'label' => __('Returns'),
            'count' => $fulfilmentCustomer->number_pallet_returns
        ];
        foreach (PalletReturnStateEnum::cases() as $case) {
            $stats['pallet_return']['cases'][$case->value] = [
                'value' => $case->value,
                'icon'  => PalletReturnStateEnum::stateIcon()[$case->value],
                'count' => PalletReturnStateEnum::count($fulfilmentCustomer)[$case->value],
                'label' => PalletReturnStateEnum::labels()[$case->value]
            ];
        }

        $stats['invoice'] = [
            'label' => __('Invoice'),
            'count' => $fulfilmentCustomer->customer->stats->number_invoices,
            // 'tooltip'     => __('Pallets in warehouse'),
            // 'description' => __('in warehouse'),
        ];

        return $stats;
    }

    public function getBreadcrumbs(): array
    {
        return
            array_merge(
                ShowRetinaDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'retina.sysadmin.dashboard'
                            ],
                            'label' => __('Account'),
                        ]
                    ]
                ]
            );
    }
}
