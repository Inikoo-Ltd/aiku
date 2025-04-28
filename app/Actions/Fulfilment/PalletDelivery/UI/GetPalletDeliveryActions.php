<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 09 Apr 2025 18:13:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletDelivery\UI;

use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\PalletDelivery\PalletDeliveryStateEnum;
use App\Enums\Fulfilment\RecurringBill\RecurringBillStatusEnum;
use App\Models\Fulfilment\PalletDelivery;
use Lorisleiva\Actions\Concerns\AsObject;

class GetPalletDeliveryActions
{
    use asObject;

    private bool $isSupervisor = false;
    private string $addIcon = 'fal fa-plus';
    private string $deleteIcon = 'fal fa-trash-alt';

    public function handle(PalletDelivery $palletDelivery, $canEdit = false, $isSupervisor = false): array
    {
        $this->isSupervisor = $isSupervisor;

        $pdfButton = [
            'type'   => 'button',
            'style'  => 'tertiary',
            'label'  => 'PDF',
            'target' => '_blank',
            'icon'   => 'fal fa-file-pdf',
            'key'    => 'action',
            'route'  => [
                'name'       => 'grp.models.pallet-delivery.pdf',
                'parameters' => [
                    'palletDelivery' => $palletDelivery->id
                ]
            ]
        ];

        if ($canEdit) {
            $actions = match ($palletDelivery->state) {
                PalletDeliveryStateEnum::IN_PROCESS => $this->inProcessActions($palletDelivery),
                PalletDeliveryStateEnum::SUBMITTED => $this->submittedActions($palletDelivery),
                PalletDeliveryStateEnum::CONFIRMED => $this->confirmedActions($palletDelivery),
                PalletDeliveryStateEnum::RECEIVED => $this->receivedActions($palletDelivery),
                PalletDeliveryStateEnum::BOOKING_IN => $this->bookingInActions($palletDelivery),
                PalletDeliveryStateEnum::BOOKED_IN => $this->booedInActions($palletDelivery),
                default => []
            };

            if (!in_array($palletDelivery->state, [
                PalletDeliveryStateEnum::IN_PROCESS,
                PalletDeliveryStateEnum::SUBMITTED
            ])) {
                $actions = array_merge($actions, [$pdfButton]);
            } else {
                $actions = array_merge([
                    [
                        'type'    => 'button',
                        'style'   => 'red_outline',
                        'tooltip' => __('Delete'),
                        'icon'    => $this->deleteIcon,
                        'key'     => 'delete_delivery',
                        'ask_why' => true,
                        'route'   => [
                            'method'     => 'patch',
                            'name'       => 'grp.models.pallet-delivery.delete',
                            'parameters' => [
                                'palletDelivery' => $palletDelivery->id
                            ]
                        ]
                    ]
                ], $actions);
            }
        } else {
            $actions = [];
        }


        return $actions;
    }

    protected function inProcessActions(PalletDelivery $palletDelivery): array
    {
        return [
            [
                'type'   => 'buttonGroup',
                'key'    => 'upload-add',
                'button' => [
                    [
                        'type'    => 'button',
                        'style'   => 'secondary',
                        'icon'    => ['fal', 'fa-upload'],
                        'label'   => '',
                        'key'     => 'upload',
                        'tooltip' => __('Upload pallets via spreadsheet'),
                    ],
                    [
                        'type'  => 'button',
                        'style' => 'secondary',
                        'icon'  => ['far', 'fa-layer-plus'],
                        'label' => 'multiple',
                        'key'   => 'multiple',
                        'route' => [
                            'name'       => 'grp.models.pallet-delivery.multiple-pallets.store',
                            'parameters' => [
                                'palletDelivery' => $palletDelivery->id
                            ]
                        ]
                    ],
                    [
                        'type'    => 'button',
                        'style'   => 'secondary',
                        'icon'    => $this->addIcon,
                        'label'   => __('add storage'),
                        'key'     => 'add-pallet',
                        'tooltip' => __('Add carton, pallet, or oversize goods'),
                        'route'   => [
                            'name'       => 'grp.models.pallet-delivery.pallet.store',
                            'parameters' => [
                                'palletDelivery' => $palletDelivery->id
                            ]
                        ]
                    ],
                    $this->addService($palletDelivery),
                    $this->addGoods($palletDelivery)
                ]
            ],
            ($palletDelivery->pallets()->count() > 0) ?
                [
                    'type'    => 'button',
                    'style'   => 'save',
                    'tooltip' => __('Submit'),
                    'label'   => __('submit'),
                    'key'     => 'action',
                    'route'   => [
                        'method'     => 'post',
                        'name'       => 'grp.models.pallet-delivery.submit_and_confirm',
                        'parameters' => [
                            'palletDelivery' => $palletDelivery->id
                        ]
                    ]
                ] : [],
        ];
    }

    protected function submittedActions(PalletDelivery $palletDelivery): array
    {
        return [
            $this->addServiceAndGoods($palletDelivery),
            [
                'type'    => 'button',
                'style'   => 'save',
                'tooltip' => __('Confirm'),
                'label'   => __('confirm'),
                'key'     => 'action',
                'route'   => [
                    'method'     => 'post',
                    'name'       => 'grp.models.pallet-delivery.confirm',
                    'parameters' => [
                        'palletDelivery' => $palletDelivery->id
                    ]
                ]
            ]
        ];
    }

    protected function confirmedActions(PalletDelivery $palletDelivery): array
    {
        return [
            $this->addServiceAndGoods($palletDelivery),
            [
                'type'    => 'button',
                'style'   => 'negative',
                'icon'    => 'fal fa-times',
                'tooltip' => __('Cancel the delivery'),
                'label'   => __('cancel'),
                'key'     => 'action',
                'route'   => [
                    'method'     => 'post',
                    'name'       => 'grp.models.pallet-delivery.cancel',
                    'parameters' => [
                        'palletDelivery' => $palletDelivery->id
                    ]
                ]
            ],
            [
                'type'    => 'button',
                'style'   => 'primary',
                'icon'    => 'fal fa-check',
                'tooltip' => __('Mark as received'),
                'label'   => __('receive'),
                'key'     => 'action',
                'route'   => [
                    'method'     => 'post',
                    'name'       => 'grp.models.pallet-delivery.received',
                    'parameters' => [
                        'palletDelivery' => $palletDelivery->id
                    ]
                ]
            ]
        ];
    }

    protected function receivedActions(PalletDelivery $palletDelivery): array
    {
        return [
            $this->bookedInDelete($palletDelivery),
            [
                'type'    => 'button',
                'style'   => 'edit',
                'tooltip' => __('Edit'),
                'key'     => 'action',
                'route'   => [
                    'method'     => 'get',
                    'name'       => 'grp.org.fulfilments.show.crm.customers.show.pallet_deliveries.edit',
                    'parameters' => [
                        'organisation'       => $palletDelivery->organisation->slug,
                        'fulfilment'         => $palletDelivery->fulfilment->slug,
                        'fulfilmentCustomer' => $palletDelivery->fulfilmentCustomer->slug,
                        'palletDelivery'     => $palletDelivery->slug
                    ]
                ]
            ],
            $this->addServiceAndGoods($palletDelivery),
            [
                'type'    => 'button',
                'style'   => 'tertiary',
                'icon'    => 'fal fa-undo-alt',
                'tooltip' => __('Revert to Confirmed'),
                'label'   => __('Revert to Confirmed'),
                'key'     => 'revert',
                'route'   => [
                    'method'     => 'post',
                    'name'       => 'grp.models.pallet-delivery.confirm',
                    'parameters' => [
                        'palletDelivery' => $palletDelivery->id
                    ]
                ]
            ],
            [
                'type'    => 'button',
                'style'   => 'primary',
                'icon'    => 'fal fa-clipboard',
                'tooltip' => __('Start booking'),
                'label'   => __('start booking'),
                'key'     => 'action',
                'route'   => [
                    'method'     => 'post',
                    'name'       => 'grp.models.pallet-delivery.booking',
                    'parameters' => [
                        'palletDelivery' => $palletDelivery->id
                    ]
                ]
            ],
        ];
    }

    protected function bookingInActions(PalletDelivery $palletDelivery): array
    {
        $numberPalletsStateBookingIn = $palletDelivery->pallets()->where('state', PalletStateEnum::BOOKING_IN)->count();
        $numberPalletsRentalNotSet   = $palletDelivery->pallets()->whereNull('rental_id')->count();

        return [
            $this->bookedInDelete($palletDelivery),
            $this->addServiceAndGoods($palletDelivery),
            ($numberPalletsStateBookingIn == 0 && $numberPalletsRentalNotSet == 0) ? [
                'type'    => 'button',
                'style'   => 'primary',
                'icon'    => 'fal fa-check',
                'tooltip' => __('Confirm booking'),
                'label'   => __('Finish booking'),
                'key'     => 'action',
                'route'   => [
                    'method'     => 'post',
                    'name'       => 'grp.models.pallet-delivery.booked-in',
                    'parameters' => [
                        'palletDelivery' => $palletDelivery->id
                    ]
                ]
            ] : null,
        ];
    }

    public function booedInActions(PalletDelivery $palletDelivery): array
    {
        return [
            $this->bookedInDelete($palletDelivery),
            isset($palletDelivery->recurringBill) && $palletDelivery->recurringBill->status == RecurringBillStatusEnum::CURRENT ? $this->addServiceAndGoods($palletDelivery) : []
        ];
    }


    protected function addServiceAndGoods(PalletDelivery $palletDelivery): array
    {
        return [
            'type'   => 'buttonGroup',
            'key'    => 'upload-add',
            'button' => [
                $this->addService($palletDelivery),
                $this->addGoods($palletDelivery)
            ]
        ];
    }

    protected function addService(PalletDelivery $palletDelivery): array
    {
        return [
            'type'    => 'button',
            'style'   => 'secondary',
            'icon'    => $this->addIcon,
            'key'     => 'add-service',
            'label'   => __('service'),
            'tooltip' => __('Add service'),
            'route'   => [
                'name'       => 'grp.models.pallet-delivery.transaction.store',
                'parameters' => [
                    'palletDelivery' => $palletDelivery->id
                ]
            ]
        ];
    }

    protected function addGoods(PalletDelivery $palletDelivery): array
    {
        return [
            'type'    => 'button',
            'style'   => 'secondary',
            'icon'    => $this->addIcon,
            'key'     => 'add-physical-good',
            'label'   => __('Goods'),
            'tooltip' => __('Add a physical goods'),
            'route'   => [
                'name'       => 'grp.models.pallet-delivery.transaction.store',
                'parameters' => [
                    'palletDelivery' => $palletDelivery->id
                ]
            ]
        ];
    }

    protected function bookedInDelete(PalletDelivery $palletDelivery): array
    {
        if (!$this->isSupervisor) {
            return [
                'supervisor' => false,
                'supervisors_route' => [
                    'method'     => 'get',
                    'name'       => 'grp.json.fulfilment.supervisors.index',
                    'parameters' => [
                        'fulfilment' => $palletDelivery->fulfilment->slug
                    ]
                ],
                'type'    => 'button',
                'style'   => 'red_outline',
                'tooltip' => __('Delete'),
                'icon'    => $this->deleteIcon,
                'key'     => 'delete_booked_in',
                'ask_why' => true,
                'route'   => [
                    'method'     => 'delete',
                    'name'       => 'grp.models.pallet-delivery.booked-in-delete',
                    'parameters' => [
                        'palletDelivery' => $palletDelivery->id
                    ]
                ]
            ];
        }


        return [
            'supervisor' => true,
            'type'    => 'button',
            'style'   => 'red_outline',
            'tooltip' => __('Delete'),
            'icon'    => $this->deleteIcon,
            'key'     => 'delete_booked_in',
            'ask_why' => true,
            'route'   => [
                'method'     => 'delete',
                'name'       => 'grp.models.pallet-delivery.booked-in-delete',
                'parameters' => [
                    'palletDelivery' => $palletDelivery->id
                ]
            ]
        ];
    }


}
