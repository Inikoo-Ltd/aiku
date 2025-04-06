<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 05 Apr 2025 14:13:18 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletReturn\UI;

use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Enums\Fulfilment\RecurringBill\RecurringBillStatusEnum;
use App\Models\Fulfilment\PalletReturn;
use Lorisleiva\Actions\Concerns\AsObject;

class GetPalletReturnActions
{
    use AsObject;

    public function handle(PalletReturn $palletReturn, $canEdit = false, $isSupervisor = false): array
    {
        if ($canEdit) {
            $actions = match ($palletReturn->state) {
                PalletReturnStateEnum::IN_PROCESS => $this->getPalletReturnInProcessActions($palletReturn),
                PalletReturnStateEnum::SUBMITTED => $this->getPalletReturnSubmittedActions($palletReturn),
                PalletReturnStateEnum::CONFIRMED => $this->getPalletReturnConfirmedActions($palletReturn),
                PalletReturnStateEnum::PICKING => $this->getPalletReturnPickingActions($palletReturn),
                PalletReturnStateEnum::PICKED => $this->getPalletReturnPickedActions($palletReturn),
                default => []
            };

            $actions = $this->addServicesActions($palletReturn, $actions);
            $actions = $this->addEarlyDelete($palletReturn, $actions);
        } else {
            $actions = [];
        }

        return $this->addPdf($palletReturn, $actions);
    }


    public function getPalletReturnInProcessActions(PalletReturn $palletReturn): array
    {
        if ($palletReturn->type == PalletReturnTypeEnum::PALLET) {
            $isDisabled = !($palletReturn->estimated_delivery_date);
            if ($palletReturn->pallets()->count() < 1) {
                $tooltipSubmit = !($palletReturn->estimated_delivery_date) ? __('Select estimated date before submit') : __('Select pallet before submit');
            } else {
                $tooltipSubmit = !($palletReturn->estimated_delivery_date) ? __('Select estimated date before submit') : __('Submit');
            }

            $buttonSubmit = [
                'type'     => 'button',
                'style'    => 'save',
                'tooltip'  => $tooltipSubmit,
                'label'    => __('Submit').' ('.$palletReturn->stats->number_pallets.')',
                'key'      => 'submit-pallet',
                'route'    => [
                    'method'     => 'post',
                    'name'       => 'grp.models.fulfilment-customer.pallet-return.submit_and_confirm',
                    'parameters' => [
                        'fulfilmentCustomer' => $palletReturn->fulfilmentCustomer->id,
                        'palletReturn'       => $palletReturn->id
                    ]
                ],
                'disabled' => $isDisabled
            ];
        } else {
            $isDisabled = false;
            if ($palletReturn->pallets()->count() < 1) {
                $tooltipSubmit = __('Select Customer\'s SKU before submit');
                $isDisabled    = true;
            } else {
                $tooltipSubmit = __('Submit');
            }

            $buttonSubmit = [
                'type'     => 'button',
                'style'    => 'save',
                'tooltip'  => $tooltipSubmit,
                'label'    => __('Submit').' ('.$palletReturn->storedItems()->count().')',
                'key'      => 'submit',
                'route'    => [
                    'method'     => 'post',
                    'name'       => 'grp.models.fulfilment-customer.pallet-return.submit_and_confirm',
                    'parameters' => [
                        'fulfilmentCustomer' => $palletReturn->fulfilmentCustomer->id,
                        'palletReturn'       => $palletReturn->id
                    ]
                ],
                'disabled' => $isDisabled
            ];
        }

        return [
            [
                'type'    => 'button',
                'style'   => 'tertiary',
                'icon'    => 'fal fa-upload',
                'label'   => __('upload'),
                'tooltip' => __('Upload file')
            ],
            $buttonSubmit
        ];
    }

    public function getPalletReturnSubmittedActions(PalletReturn $palletReturn): array
    {
        return [
            [
                'type'    => 'button',
                'style'   => 'save',
                'tooltip' => __('confirm'),
                'label'   => __('confirm'),
                'key'     => 'confirm',
                'route'   => [
                    'method'     => 'post',
                    'name'       => 'grp.models.fulfilment-customer.pallet-return.confirm',
                    'parameters' => [
                        'organisation'       => $palletReturn->organisation->slug,
                        'fulfilment'         => $palletReturn->fulfilment->slug,
                        'fulfilmentCustomer' => $palletReturn->fulfilmentCustomer->id,
                        'palletReturn'       => $palletReturn->id
                    ]
                ]
            ]
        ];
    }

    public function getPalletReturnConfirmedActions(PalletReturn $palletReturn): array
    {
        $actions = [];
        if ($palletReturn->type == PalletReturnTypeEnum::PALLET) {
            $actions = array_merge(
                $actions,
                [
                    'type'    => 'button',
                    'style'   => 'negative',
                    'tooltip' => __('In Process'),
                    'label'   => __('In Process'),
                    'key'     => 'in process',
                    'icon'    => 'fal fa-undo',
                    'route'   => [
                        'method'     => 'post',
                        'name'       => 'grp.models.pallet-return.revert-to-in-process',
                        'parameters' => [
                            'palletReturn' => $palletReturn->id
                        ]
                    ]
                ]
            );
        }

        return array_merge(
            $actions,
            [
                'type'    => 'button',
                'style'   => 'save',
                'tooltip' => __('Start picking'),
                'label'   => __('start picking'),
                'key'     => 'start picking',
                'route'   => [
                    'method'     => 'post',
                    'name'       => 'grp.models.fulfilment-customer.pallet-return.picking',
                    'parameters' => [
                        'organisation'       => $palletReturn->organisation->slug,
                        'fulfilment'         => $palletReturn->fulfilment->slug,
                        'fulfilmentCustomer' => $palletReturn->fulfilmentCustomer->id,
                        'palletReturn'       => $palletReturn->id
                    ]
                ]
            ]
        );
    }

    public function getPalletReturnPickingActions(PalletReturn $palletReturn): array
    {
        $actions = [];

        if ($palletReturn->type == PalletReturnTypeEnum::PALLET) {
            $actions = array_merge(
                $actions,
                [
                    'type'    => 'button',
                    'style'   => 'save',
                    'tooltip' => __('Set all pending as picked'),
                    'label'   => __('pick all'),
                    'key'     => 'pick all',
                    'route'   => [
                        'method'     => 'post',
                        'name'       => 'grp.models.fulfilment-customer.pallet-return.picked',
                        'parameters' => [
                            'organisation'       => $palletReturn->organisation->slug,
                            'fulfilment'         => $palletReturn->fulfilment->slug,
                            'fulfilmentCustomer' => $palletReturn->fulfilmentCustomer->id,
                            'palletReturn'       => $palletReturn->id
                        ]
                    ]
                ]
            );
        }
        //else{
        //todo , Kirin if this will not be implemented remove it and delete the action/route
        //                $actions = array_merge(
        //                    $actions,
        //                    [
        //                        'type'    => 'button',
        //                        'style'   => 'save',
        //                        'tooltip' => __('Set all pending as picked'),
        //                        'label'   => __('pick all*'),
        //                        'key'     => 'pick all',
        //                        'route'   => [
        //                            'method'     => 'post',
        //                            'name'       => 'grp.models.pallet-return.pick_all_with_stored_items',
        //                            'parameters' => [
        //                                'palletReturn' => $palletReturn->id
        //                            ]
        //                        ]
        //                    ]
        //                );
        //}

        return $actions;
    }

    public function getPalletReturnPickedActions(PalletReturn $palletReturn): array
    {
        return [
            [
                'type'    => 'button',
                'style'   => 'save',
                'tooltip' => __('Set as dispatched'),
                'label'   => __('Dispatch'),
                'key'     => 'Dispatching',
                'route'   => [
                    'method'     => 'post',
                    'name'       => 'grp.models.pallet-return.dispatch',
                    'parameters' => [
                        'palletReturn' => $palletReturn->id
                    ]
                ]
            ]
        ];
    }

    public function addServicesActions(PalletReturn $palletReturn, array $actions): array
    {
        if ($palletReturn->recurringBill->status == RecurringBillStatusEnum::CURRENT) {
            $actions = array_merge(
                $actions,
                [
                    'type'   => 'buttonGroup',
                    'key'    => 'upload-add',
                    'button' => [
                        [
                            'type'    => 'button',
                            'style'   => 'secondary',
                            'icon'    => 'fal fa-plus',
                            'label'   => __('add service'),
                            'key'     => 'add-service',
                            'tooltip' => __('Add single service'),
                            'route'   => [
                                'name'       => 'grp.models.pallet-return.transaction.store',
                                'parameters' => [
                                    'palletReturn' => $palletReturn->id
                                ]
                            ]
                        ],
                        [
                            'type'    => 'button',
                            'style'   => 'secondary',
                            'icon'    => 'fal fa-plus',
                            'key'     => 'add-physical-good',
                            'label'   => __('add physical good'),
                            'tooltip' => __('Add physical good'),
                            'route'   => [
                                'name'       => 'grp.models.pallet-return.transaction.store',
                                'parameters' => [
                                    'palletReturn' => $palletReturn->id
                                ]
                            ]
                        ],
                    ]
                ]
            );
        }

        return $actions;
    }

    public function addEarlyDelete(PalletReturn $palletReturn, array $actions): array
    {
        if (in_array($palletReturn->state, [
            PalletReturnStateEnum::IN_PROCESS,
            PalletReturnStateEnum::SUBMITTED
        ])) {
            $actions = array_merge([
                [
                    'type'    => 'button',
                    'style'   => 'delete',
                    'tooltip' => __('delete'),
                    'key'     => 'delete_return',
                    'route'   => [
                        'method'     => 'patch',
                        'name'       => 'grp.models.pallet-return.delete',
                        'parameters' => [
                            'palletReturn' => $palletReturn->id
                        ]
                    ]
                ]
            ], $actions);
        }

        return $actions;
    }

    public function addPdf(PalletReturn $palletReturn, array $actions): array
    {
        if (!in_array($palletReturn->state, [
            PalletReturnStateEnum::IN_PROCESS,
            PalletReturnStateEnum::SUBMITTED
        ])) {
            $actions = array_merge(
                [
                    'type'   => 'button',
                    'style'  => 'tertiary',
                    'label'  => 'PDF',
                    'target' => '_blank',
                    'icon'   => 'fal fa-file-pdf',
                    'key'    => 'action',
                    'route'  => [
                        'name'       => 'grp.models.pallet-return.pdf',
                        'parameters' => [
                            'palletReturn' => $palletReturn->id
                        ]
                    ]
                ],
                $actions
            );
        }

        return $actions;
    }


}
