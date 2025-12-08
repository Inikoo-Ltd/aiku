<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jan 2024 15:25:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Fulfilment\PalletReturn;

use App\Enums\EnumHelperTrait;

enum PalletReturnTypeEnum: string
{
    use EnumHelperTrait;

    case PALLET = 'pallet';
    case STORED_ITEM = 'stored_item';
    case DROPSHIPPING = 'dropshipping';

    public static function labels($forElements = false): array
    {
        return [
            'pallet' => __('Pallet'),
            'stored_item' => __('Stored Item'),
            'dropshipping' => __('Dropshipping'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'pallet' => [
                'tooltip' => __('Pallet'),
                'icon' => 'fal fa-pallet',
            ],
            'stored_item' => [
                'tooltip' => __('Stored Item'),
                'icon' => 'fal fa-narwhal',
            ],
            'dropshipping' => [
                'tooltip' => __('Dropshipping'),
                'icon' => 'fal fa-box',
            ],
        ];
    }
}
