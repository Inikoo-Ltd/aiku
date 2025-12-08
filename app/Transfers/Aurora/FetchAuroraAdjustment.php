<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Sept 2024 16:49:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Actions\Helpers\CurrencyExchange\GetHistoricCurrencyExchange;
use App\Enums\Ordering\Adjustment\AdjustmentTypeEnum;
use Illuminate\Support\Facades\DB;

class FetchAuroraAdjustment extends FetchAurora
{
    protected function parseModel(): void
    {

        if ($this->auroraModelData->{'Order Store Key'} != '') {
            $this->parsedData['shop'] = $this->parseShop($this->organisation->id.':'.$this->auroraModelData->{'Order Store Key'});
            $date = $this->parseDateTime($this->auroraModelData->{'Order Date'});
        } else {
            $date = $this->parseDateTime($this->auroraModelData->{'Invoice Date'});
            $dataWithInvoice = DB::connection('aurora')
                ->table('Order No Product Transaction Fact')
                ->leftJoin('Invoice Dimension', 'Order No Product Transaction Fact.Invoice Key', '=', 'Invoice Dimension.Invoice Key')
                ->where('Order No Product Transaction Fact Key', $this->auroraModelData->{'Order No Product Transaction Fact Key'})->first();

            $this->parsedData['shop'] = $this->parseShop($this->organisation->id.':'.$dataWithInvoice->{'Invoice Store Key'});
        }

        $netAmount = $this->auroraModelData->{'Transaction Invoice Net Amount'};
        $taxAmount = $this->auroraModelData->{'Transaction Invoice Tax Amount'};

        $orgExchange = GetHistoricCurrencyExchange::run($this->parsedData['shop']->currency, $this->parsedData['shop']->organisation->currency, $date);
        $groupExchange = GetHistoricCurrencyExchange::run($this->parsedData['shop']->currency, $this->parsedData['shop']->group->currency, $date);

        $type = match ($this->auroraModelData->{'Transaction Type'}) {
            'Credit' => AdjustmentTypeEnum::CREDIT,
            default => AdjustmentTypeEnum::ERROR_NET
        };

        if ($type == AdjustmentTypeEnum::ERROR_NET and $this->auroraModelData->{'Transaction Description'} == 'Tax Adjustment') {
            $type = AdjustmentTypeEnum::ERROR_TAX;

            $orgTaxAmount = $orgExchange * $taxAmount;
            $groupTaxAmount = $groupExchange * $taxAmount;
        } else {
            $taxAmount = null;
            $orgTaxAmount = null;
            $groupTaxAmount = null;
        }

        $this->parsedData['adjustment'] = [
            'net_amount' => $netAmount,
            'org_net_amount' => $orgExchange * $netAmount,
            'group_net_amount' => $groupExchange * $netAmount,
            'tax_amount' => $taxAmount,
            'org_tax_amount' => $orgTaxAmount,
            'group_tax_amount' => $groupTaxAmount,
            'type' => $type,

            'source_id' => $this->organisation->id.':'.$this->auroraModelData->{'Order No Product Transaction Fact Key'},
            'fetched_at' => now(),
            'last_fetched_at' => now(),
        ];
    }

    protected function fetchData($id): ?object
    {
        return DB::connection('aurora')
            ->table('Order No Product Transaction Fact')
            ->leftJoin('Order Dimension', 'Order No Product Transaction Fact.Order Key', '=', 'Order Dimension.Order Key')
            ->where('Order No Product Transaction Fact Key', $id)->first();
    }
}
