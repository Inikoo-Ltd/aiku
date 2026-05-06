<?php

namespace App\Enums\GoodsIn\ReturnDeliveryNoteItem;

use App\Enums\EnumHelperTrait;

enum ReturnDeliveryNoteItemStateEnum: string
{
    use EnumHelperTrait;

    //Block to do
    case UNASSIGNED = 'unassigned';

    // Block Picking
    case HANDLING = 'handling';

    // Block Received
    // case NOT_RETURNED = 'not_returned';
    // case DAMAGED = 'damaged';
    case PROCESSED = 'processed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::UNASSIGNED    => 'Item is on queue',
            self::HANDLING      => 'Item is handled',
            // self::NOT_RETURNED  => 'Item is not returned',
            // self::DAMAGED       => 'Item is damaged',
            self::PROCESSED      => 'Item is processed',
            self::CANCELLED     => 'Item is cancelled',
        };
    }

    public function stateIcon(): array
    {
        return match ($this) {
            self::UNASSIGNED    => [
                'tooltip' => __('Unassigned'),
                'icon'    => 'fal fa-chair',
                'class'   => 'text-gray-500',  // Color for normal icon (Aiku)
                'color'   => 'grey',  // Color for box (Retina)
                'app'     => [
                    'name' => 'chair',
                    'type' => 'font-awesome-5'
                ]
            ],
            self::HANDLING      => [
                'tooltip' => __('Handling'),
                'icon'    => 'fal fa-hand-paper',
                'class'   => 'text-gray-500',
                'color'   => 'gray',
                'app'     => [
                    'name' => 'check',
                    'type' => 'font-awesome-5'
                ]
            ],
            // self::NOT_RETURNED  => [
            //     'tooltip' => __('Not Returned'),
            //     'icon'    => 'fal fa-minus-circle',
            //     'class'   => 'text-red-500',
            //     'color'   => 'red',
            //     'app'     => [
            //         'name' => 'times',
            //         'type' => 'font-awesome-5'
            //     ]
            // ],
            // self::DAMAGED       => [
            //     'tooltip' => __('Damaged'),
            //     'icon'    => 'fal fa-claw-marks',
            //     'class'   => 'text-red-500',
            //     'color'   => 'red',
            //     'app'     => [
            //         'name' => 'times',
            //         'type' => 'font-awesome-5'
            //     ]
            // ],
            self::PROCESSED      => [
                'tooltip' => __('Processed'),
                'icon'    => 'fal fa-check-circle',
                'class'   => 'text-green-500',
                'color'   => 'green',
                'app'     => [
                    'name' => 'times',
                    'type' => 'font-awesome-5'
                ]
            ],
            self::CANCELLED     => [
                'tooltip' => __('Cancelled'),
                'icon'    => 'fal fa-times',
                'class'   => 'text-gray-500',
                'color'   => 'gray',
                'app'     => [
                    'name' => 'times',
                    'type' => 'font-awesome-5'
                ]
            ],
        };
    }
}
