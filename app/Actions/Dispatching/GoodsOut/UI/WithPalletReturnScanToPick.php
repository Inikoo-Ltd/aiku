<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Aug 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\GoodsOut\UI;

use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Models\Fulfilment\PalletReturn;

trait WithPalletReturnScanToPick
{
    /**
     * The scan panel exists only while the return is being picked and only for organisations that
     * opted into scanning, the same gate the delivery note picking screens use.
     *
     * @return array{scan_route: array{name: string, parameters: array{palletReturn: int}, method: string}}|null
     */
    protected function getScanToPick(PalletReturn $palletReturn): ?array
    {
        if (
            $palletReturn->state != PalletReturnStateEnum::PICKING
            || !data_get($this->organisation->settings, 'orders.allow_scan_to_pick', false)
        ) {
            return null;
        }

        return [
            'scan_route' => [
                'name'       => 'grp.json.pallet_return.pick_by_scan',
                'parameters' => [
                    'palletReturn' => $palletReturn->id,
                ],
                'method'     => 'post',
            ],
        ];
    }
}
