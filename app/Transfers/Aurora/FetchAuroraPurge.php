<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Nov 2024 15:15:51 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Actions\Helpers\CurrencyExchange\GetHistoricCurrencyExchange;
use App\Enums\Ordering\Purge\PurgeStateEnum;
use App\Enums\Ordering\Purge\PurgeTypeEnum;
use Illuminate\Support\Facades\DB;

class FetchAuroraPurge extends FetchAurora
{
    protected function parseModel(): void
    {
        $state = match ($this->auroraModelData->{'Order Basket Purge State'}) {
            'In Process' => PurgeStateEnum::IN_PROCESS,
            'Purging' => PurgeStateEnum::PURGING,
            'Finished' => PurgeStateEnum::FINISHED,
            default => PurgeStateEnum::CANCELLED
        };

        $shop = $this->parseShop($this->organisation->id.':'.$this->auroraModelData->{'Order Basket Purge Store Key'});

        $date = $this->parseDatetime($this->auroraModelData->{'Order Basket Purge Date'});

        $this->parsedData['shop'] = $shop;
        $this->parsedData['purge'] = [
            'state' => $state,
            'source_id' => $this->organisation->id.':'.$this->auroraModelData->{'Order Basket Purge Key'},
            'fetched_at' => now(),
            'last_fetched_at' => now(),
            'type' => PurgeTypeEnum::MANUAL,
            'created_at' => $date,
            'inactive_days' => $this->auroraModelData->{'Order Basket Purge Inactive Days'},
        ];

        if ($this->parseDatetime($this->auroraModelData->{'Order Basket Purge Start Purge Date'})) {
            $this->parsedData['purge']['start_at'] = $this->parseDatetime($this->auroraModelData->{'Order Basket Purge Start Purge Date'});
        }

        if ($this->parseDatetime($this->auroraModelData->{'Order Basket Purge Start Purge Date'})) {
            $this->parsedData['purge']['end_at'] = $this->parseDatetime($this->auroraModelData->{'Order Basket Purge End Purge Date'});
        }

        if ($this->auroraModelData->{'Order Basket Purge User Key'}) {
            $userId = $this->parseUser($this->organisation->id.':'.$this->auroraModelData->{'Order Basket Purge User Key'});
            if ($userId) {
                $this->parsedData['purge']['user_id'] = $userId->id;
            }
        }

        $orgExchange = GetHistoricCurrencyExchange::run($shop->currency, $shop->organisation->currency, $date);
        $groupExchange = GetHistoricCurrencyExchange::run($shop->currency, $shop->group->currency, $date);

        $estimatedNetAmount = $this->auroraModelData->{'Order Basket Purge Estimated Amount'};
        $netAmount = $this->auroraModelData->{'Order Basket Purge Purged Amount'};

        $this->parsedData['purge_stats'] = [
            'estimated_number_transactions' => $this->auroraModelData->{'Order Basket Purge Estimated Transactions'},
            'estimated_net_amount' => $estimatedNetAmount,
            'estimated_org_net_amount' => $estimatedNetAmount * $orgExchange,
            'estimated_grp_net_amount' => $estimatedNetAmount * $groupExchange,
            'number_purged_transactions' => $this->auroraModelData->{'Order Basket Purge Purged Transactions'},
            'purged_net_amount' => $netAmount,
            'purged_org_net_amount' => $netAmount * $orgExchange,
            'purged_grp_net_amount' => $netAmount * $groupExchange,
        ];
    }

    protected function fetchData($id): ?object
    {
        return DB::connection('aurora')
            ->table('Order Basket Purge Dimension')
            ->where('Order Basket Purge Key', $id)->first();
    }
}
