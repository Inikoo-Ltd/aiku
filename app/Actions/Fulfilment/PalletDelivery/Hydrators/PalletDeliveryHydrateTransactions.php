<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 20 Jul 2023 09:57:45 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletDelivery\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Fulfilment\FulfilmentTransaction\FulfilmentTransactionTypeEnum;
use App\Models\Fulfilment\PalletDelivery;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class PalletDeliveryHydrateTransactions implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(PalletDelivery $palletDelivery): string
    {
        return $palletDelivery->id;
    }

    public function handle(PalletDelivery $palletDelivery): void
    {
        $stats = [
            'number_transactions'   => $palletDelivery->transactions()->count(),
            'number_services'       => $palletDelivery->transactions()->where('type', FulfilmentTransactionTypeEnum::SERVICE)->count(),
            'number_physical_goods' => $palletDelivery->transactions()->where('type', FulfilmentTransactionTypeEnum::PRODUCT)->count()
        ];

        $palletDelivery->stats()->update($stats);
    }
}
