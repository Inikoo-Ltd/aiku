<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 06 Apr 2025 12:25:33 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletReturn\UI;

use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Models\Fulfilment\PalletReturn;
use Lorisleiva\Actions\Concerns\AsObject;

class GetRetinaPalletReturnActions
{
    use AsObject;

    public function handle(PalletReturn $palletReturn): array
    {
        $actions = match ($palletReturn->state) {
            PalletReturnStateEnum::IN_PROCESS => $this->getPalletReturnInProcessActions($palletReturn),
            PalletReturnStateEnum::DISPATCHED, PalletReturnStateEnum::CANCEL => [],
            default => $this->getPalletReturnDefaultActions($palletReturn)
        };

        return $this->addEarlyDelete($palletReturn, $actions);
    }

    public function addEarlyDelete(PalletReturn $palletReturn, array $actions): array
    {
        if (in_array($palletReturn->state, [
            PalletReturnStateEnum::IN_PROCESS,
            PalletReturnStateEnum::SUBMITTED
        ])) {
            $actions = array_merge(
                $actions,
                [
                    [
                        'type'    => 'button',
                        'style'   => 'delete',
                        'tooltip' => __('delete'),
                        'label'   => __('delete'),
                        'key'     => 'delete_return',
                        'ask_why' => false,
                        'route'   => [
                            'method'     => 'patch',
                            'name'       => 'retina.models.pallet-return.delete',
                            'parameters' => [
                                'palletReturn' => $palletReturn->id
                            ]
                        ]
                    ]
                ]
            );
        }

        return $actions;
    }

    public function getPalletReturnDefaultActions(PalletReturn $palletReturn): array
    {
        return [
            [
                'type'    => 'button',
                'style'   => 'negative',
                'icon'    => 'fal fa-times',
                'tooltip' => __('cancel'),
                'label'   => __('cancel return'),
                'key'     => 'action',
                'route'   => [
                    'method'     => 'post',
                    'name'       => 'retina.models.pallet-return.cancel',
                    'parameters' => [
                        'palletReturn' => $palletReturn->id
                    ]
                ]
            ]
        ];
    }

    public function getPalletReturnInProcessActions(PalletReturn $palletReturn): array
    {
        $actions = [];
        if ($palletReturn->type == PalletReturnTypeEnum::PALLET) {
            $actions[] = [
                [
                    'type'    => 'button',
                    'style'   => 'tertiary',
                    'icon'    => 'fal fa-upload',
                    'label'   => __('upload'),
                    'tooltip' => __('Upload file')
                ],
                [
                    'type' => 'button',
                    'key'  => 'modal-add-pallet',
                ],
            ];
        }

        if ($palletReturn->pallets()->count() > 0) {
            $actions[] = 
                [
                    'type'     => 'button',
                    'style'    => 'save',
                    'tooltip'  => !($palletReturn->estimated_delivery_date) ? __('Select estimated date before submit') : __('submit'),
                    'label'    => __('submit'),
                    'key'      => 'action',
                    'disabled' => !($palletReturn->estimated_delivery_date),
                    'route'    => [
                        'method'     => 'post',
                        'name'       => 'retina.models.pallet-return.submit',
                        'parameters' => [
                            'palletReturn' => $palletReturn->id
                        ]
                    ]
                ];

        }

        return $actions;
    }


}
