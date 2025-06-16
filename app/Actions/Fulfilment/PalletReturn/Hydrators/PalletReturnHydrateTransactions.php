<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 05 Jul 2024 02:20:01 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletReturn\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Fulfilment\FulfilmentTransaction\FulfilmentTransactionTypeEnum;
use App\Models\Fulfilment\PalletReturn;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class PalletReturnHydrateTransactions implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(PalletReturn $palletReturn): string
    {
        return $palletReturn->id;
    }

    public function handle(PalletReturn $palletReturn): void
    {
        $stats = [
            'number_transactions'   => $palletReturn->transactions()->count(),
            'number_services'       => $palletReturn->transactions()->where('type', FulfilmentTransactionTypeEnum::SERVICE)->count(),
            'number_physical_goods' => $palletReturn->transactions()->where('type', FulfilmentTransactionTypeEnum::PRODUCT)->count()
        ];

        $palletReturn->stats()->update($stats);
    }
}
