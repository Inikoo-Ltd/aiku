<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 05 Apr 2025 14:13:18 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletReturn\UI;

use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnItemStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Enums\Fulfilment\RecurringBill\RecurringBillStatusEnum;
use App\Models\Fulfilment\PalletReturn;
use Lorisleiva\Actions\Concerns\AsObject;

class GetPalletReturnActions
{
    use AsObject;

    private bool $isSupervisor = false;
    private string $deleteIcon = 'fal fa-trash-alt';

    public function handle(PalletReturn $palletReturn, $canEdit = false, $isSupervisor = false): array
    {
        $this->isSupervisor = $isSupervisor;

        if ($palletReturn->deleted_at) {
            return $this->addPdf($palletReturn, []);
        }

        if ($canEdit) {
            $actions = match ($palletReturn->state) {
                PalletReturnStateEnum::IN_PROCESS => $this->getPalletReturnInProcessActions($palletReturn),
                PalletReturnStateEnum::SUBMITTED => $this->getPalletReturnSubmittedActions($palletReturn),
                PalletReturnStateEnum::CONFIRMED => $this->getPalletReturnConfirmedActions($palletReturn),
                PalletReturnStateEnum::PICKING => $this->getPalletReturnPickingActions($palletReturn),
                PalletReturnStateEnum::PICKED =>  $this->getPalletReturnPickedActions($palletReturn),
                PalletReturnStateEnum::DISPATCHED => $this->getPalletReturnDispatchedActions($palletReturn),
                default => []
            };

            $actions = $this->addServicesActions($palletReturn, $actions);
            $actions = $this->addEarlyDelete($palletReturn, $actions);
        } else {
            $actions = [];
        }

        return $this->addCancelButton($palletReturn, $this->addPdf($palletReturn, $actions));
    }


    public function getPalletReturnInProcessActions(PalletReturn $palletReturn): array
    {
        if ($palletReturn->type == PalletReturnTypeEnum::PALLET) {
            $hasSelectedPallet = $palletReturn->pallets()->count() > 0;
            $isDisabled = !($palletReturn->estimated_delivery_date) || !$hasSelectedPallet;

            if (!$hasSelectedPallet) {
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
            $hasSelectedStoredItem = $palletReturn->storedItems()->count() > 0;
            $isDisabled = !($palletReturn->estimated_delivery_date) || !$hasSelectedStoredItem;

            if (!$hasSelectedStoredItem) {
                $tooltipSubmit = !($palletReturn->estimated_delivery_date) ? __('Select estimated date before submit') : __("Select Customer's SKU before submit");
            } else {
                $tooltipSubmit = !($palletReturn->estimated_delivery_date) ? __('Select estimated date before submit') : __('Submit');
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
                'label'   => __('Confirm'),
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
            $actions[] =
                [
                    'type'    => 'button',
                    'style'   => 'negative',
                    'label'   => '',
                    'tooltip' => __('Send return back to In Process'),
                    'key'     => 'in process',
                    'icon'    => 'fal fa-arrow-alt-left',
                    'route'   => [
                        'method'     => 'post',
                        'name'       => 'grp.models.pallet-return.revert-to-in-process',
                        'parameters' => [
                            'palletReturn' => $palletReturn->id
                        ]
                    ]
                ];
        }


        $actions[] =
            [
                'type'    => 'button',
                'style'   => 'save',
                'tooltip' => __('Start picking'),
                'label'   => __('Start picking'),
                'key'     => 'start picking',
                'icon'    => 'fal fa-arrow-alt-right',
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
            ];

        return $actions;
    }

    public function getPalletReturnPickingActions(PalletReturn $palletReturn): array
    {
        $actions = [];
        $baseQuery = $palletReturn->pallets()->whereNot('pallets.state', [PalletStateEnum::DISPATCHED]);
        $palletCount = (clone $baseQuery)->count();
        $completedPickingCount = (clone $baseQuery)
            ->wherePivotIn('state', [
                PalletReturnItemStateEnum::PICKED->value,
                PalletReturnItemStateEnum::NOT_PICKED->value,
            ])
            ->count();
        $canSetAsPicked = $palletCount > 0 && $palletCount === $completedPickingCount;

        if ($canSetAsPicked) {
            $actions[] = [
                'type'      => 'button',
                'style'     => 'save',
                'label'     => __('Finish picking'),
                'key'       => 'finish-picking',
                'icon'      => 'fas fa-monument',
                'iconRight' => 'fal fa-arrow-right',
                'route'     => [
                    'method'     => 'post',
                    'name'       => 'grp.models.fulfilment-customer.pallet-return.picked',
                    'parameters' => [
                        'organisation'       => $palletReturn->organisation->slug,
                        'fulfilment'         => $palletReturn->fulfilment->slug,
                        'fulfilmentCustomer' => $palletReturn->fulfilmentCustomer->id,
                        'palletReturn'       => $palletReturn->id
                    ]
                ],
            ];
        }

        if ($palletReturn->type == PalletReturnTypeEnum::PALLET) {
            // $actions[] =
            //     [
            //         'type'    => 'button',
            //         'style'   => 'save',
            //         'tooltip' => __('Set all pending as picked'),
            //         'label'   => __('Pick all'),
            //         'key'     => 'pick all',
            //         'route'   => [
            //             'method'     => 'post',
            //             'name'       => 'grp.models.fulfilment-customer.pallet-return.picked',
            //             'parameters' => [
            //                 'organisation'       => $palletReturn->organisation->slug,
            //                 'fulfilment'         => $palletReturn->fulfilment->slug,
            //                 'fulfilmentCustomer' => $palletReturn->fulfilmentCustomer->id,
            //                 'palletReturn'       => $palletReturn->id
            //             ]
            //         ]
            //     ];

            $actions[] =
                [
                    'type'    => 'button',
                    'style'   => '',
                    'tooltip' => __('PDF'),
                    'label'   => __('Pickings PDF'),
                    'key'     => 'pdf',
                    'icon'    => 'fal fa-file-pdf',
                    'route'   => [
                        'method'     => 'get',
                        'name'       => 'grp.models.pallet-return.pallet_picking.pdf',
                        'parameters' => [
                            'palletReturn' => $palletReturn->id
                        ]
                    ]
                ];
        } elseif ($palletReturn->type == PalletReturnTypeEnum::STORED_ITEM) {
            $actions[] =
                [
                    'type'    => 'button',
                    'style'   => '',
                    'tooltip' => __('PDF'),
                    'label'   => __('PDF'),
                    'key'     => 'pdf',
                    'icon'    => 'fal fa-file-pdf',
                    'route'   => [
                        'method'     => 'get',
                        'name'       => 'grp.models.pallet-return.stored_item_picking.pdf',
                        'parameters' => [
                            'palletReturn' => $palletReturn->id
                        ]
                    ]
                ];
        }


        return $actions;
    }

    public function getPalletReturnPickedActions(PalletReturn $palletReturn): array
    {
        $actions = [];
        if ($palletReturn->type == PalletReturnTypeEnum::PALLET) {

            $actions[] =
                [
                    'type'    => 'button',
                    'style'   => '',
                    'tooltip' =>  __('Picking sheet'),
                    'label'   => __('Picking sheet'),
                    'key'     => 'pdf',
                    'icon'    => 'fal fa-file-pdf',
                    'route'   => [
                        'method'     => 'get',
                        'name'       => 'grp.models.pallet-return.pallet_picking.pdf',
                        'parameters' => [
                            'palletReturn' => $palletReturn->id
                        ]
                    ]
                ];

        } elseif ($palletReturn->type == PalletReturnTypeEnum::STORED_ITEM) {
            $actions[] =
                [
                    'type'    => 'button',
                    'style'   => '',
                    'tooltip' =>  __('Picking sheet'),
                    'label'   => __('Picking sheet'),
                    'key'     => 'pdf',
                    'icon'    => 'fal fa-file-pdf',
                    'route'   => [
                        'method'     => 'get',
                        'name'       => 'grp.models.pallet-return.stored_item_picking.pdf',
                        'parameters' => [
                            'palletReturn' => $palletReturn->id
                        ]
                    ]
                ];
        }

        $actions[] =
            [
                'type'    => 'button',
                'style'   => 'negative',
                'label'   => __('Revert to Picking'),
                'tooltip' => __('Send return back to picking'),
                'key'     => 'revert-to-picking',
                'icon'    => 'fal fa-arrow-alt-left',
                'route'   => [
                    'method'     => 'post',
                    'name'       => 'grp.models.pallet-return.revert-to-picking',
                    'parameters' => [
                        'palletReturn' => $palletReturn->id
                    ]
                ]
            ];

        $requiresShipmentBeforeDispatch = !$palletReturn->is_collection && !$palletReturn->shipments()->exists();
        $dispatchTooltip = $requiresShipmentBeforeDispatch
            ? __('Please add shipment before dispatch')
            : ($palletReturn->is_collection ? __('Set as collected') : __('Set as dispatched'));
        $dispatchLabel = $palletReturn->is_collection ? __('Set as Collected') : __('Dispatch');

        $actions[] =
            [
                'type'    => 'button',
                'style'   => 'save',
                'tooltip' => $dispatchTooltip,
                'label'   => $dispatchLabel,
                'key'     => 'Dispatching',
                'route'   => [
                    'method'     => 'post',
                    'name'       => 'grp.models.pallet-return.dispatch',
                    'parameters' => [
                        'palletReturn' => $palletReturn->id
                    ]
                ],
                'disabled' => $requiresShipmentBeforeDispatch

        ];

        return $actions;



    }

    public function addServicesActions(PalletReturn $palletReturn, array $actions): array
    {
        if ($palletReturn->recurringBill && $palletReturn->recurringBill->status == RecurringBillStatusEnum::CURRENT && !$palletReturn->platform_id) {
            $actions[] =
                [
                    'type'   => 'buttonGroup',
                    'key'    => 'upload-add',
                    'button' => [
                        [
                            'type'    => 'button',
                            'style'   => 'secondary',
                            'icon'    => 'fal fa-plus',
                            'label'   => __('Service'),
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
                            'label'   => __('Physical good'),
                            'tooltip' => __('Add physical good'),
                            'route'   => [
                                'name'       => 'grp.models.pallet-return.transaction.store',
                                'parameters' => [
                                    'palletReturn' => $palletReturn->id
                                ]
                            ]
                        ],
                    ]
                ];
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
                    'label'   => 'Delete',
                    'tooltip' => __('Delete return'),
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

    public function addCancelButton(PalletReturn $palletReturn, array $actions): array
    {
        if (in_array($palletReturn->state, [
            PalletReturnStateEnum::CONFIRMED,
            PalletReturnStateEnum::PICKING,
            PalletReturnStateEnum::PICKED,
        ])) {
            $actions[] = [
                'type'   => 'button',
                'style'  => 'delete',
                'label'  => 'Cancel',
                'icon'   => 'fal fa-align-slash',
                'key'    => 'cancel-pallet-return',
                'route'  => [
                    'method'     => 'patch',
                    'name'       => 'grp.models.pallet-return.cancel',
                    'parameters' => [
                        'palletReturn' => $palletReturn->id
                    ]
                ]
            ];
        }

        return $actions;
    }

    public function addPdf(PalletReturn $palletReturn, array $actions): array
    {
        if (!in_array($palletReturn->state, [
            PalletReturnStateEnum::IN_PROCESS,
            PalletReturnStateEnum::SUBMITTED
        ])) {
            $actions[] = [
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
            ];
        }

        return $actions;
    }

    public function getPalletReturnDispatchedActions(PalletReturn $palletReturn): array
    {
        return [
            $this->dispatchedDelete($palletReturn),
        ];
    }

    protected function dispatchedDelete(PalletReturn $palletReturn): array
    {
        if (!$this->isSupervisor) {
            return [
                'supervisor'        => false,
                'supervisors_route' => [
                    'method'     => 'get',
                    'name'       => 'grp.json.fulfilment.supervisors.index',
                    'parameters' => [
                        'fulfilment' => $palletReturn->fulfilment->slug
                    ]
                ],
                'type'              => 'button',
                'style'             => 'red_outline',
                'tooltip'           => __('Delete'),
                'icon'              => $this->deleteIcon,
                'key'               => 'delete_dispatched',
                'ask_why'           => true,
                'route'             => [
                    'method'     => 'delete',
                    'name'       => 'grp.models.pallet-return.dispatched-delete',
                    'parameters' => [
                        'palletReturn' => $palletReturn->id
                    ]
                ]
            ];
        }


        return [
            'supervisor' => true,
            'type'       => 'button',
            'style'      => 'red_outline',
            'tooltip'    => __('Delete'),
            'icon'       => $this->deleteIcon,
            'key'        => 'delete_dispatched',
            'ask_why'    => true,
            'route'      => [
                'method'     => 'delete',
                'name'       => 'grp.models.pallet-return.dispatched-delete',
                'parameters' => [
                    'palletReturn' => $palletReturn->id
                ]
            ]
        ];
    }


}
