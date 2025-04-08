<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 05 Apr 2025 13:59:44 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment;

use App\Models\Fulfilment\PalletDelivery;
use App\Models\Fulfilment\PalletReturn;
use Lorisleiva\Actions\Concerns\AsObject;

class GetNotesData
{
    use AsObject;

    public function handle(PalletReturn|PalletDelivery $model): array
    {
        return [
            [
                'label'    => __('Customer'),
                'note'     => $model->customer_notes ?? '',
                'editable' => false,
                'bgColor'  => 'blue',
                'field'    => 'customer_notes'
            ],
            [
                'label'    => __('Public'),
                'note'     => $model->public_notes ?? '',
                'editable' => true,
                'bgColor'  => 'pink',
                'field'    => 'public_notes'
            ],
            [
                'label'    => __('Private'),
                'note'     => $model->internal_notes ?? '',
                'editable' => true,
                'bgColor'  => 'purple',
                'field'    => 'internal_notes'
            ],
        ];
    }
}
