<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:10 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Actions\Helpers\CurrencyExchange\GetHistoricCurrencyExchange;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Helpers\Address;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FetchAuroraInvoice extends FetchAurora
{
    protected function parseInvoiceModel($forceWithTransactions): void
    {
        $shop = $this->parseShop($this->organisation->id.':'.$this->auroraModelData->{'Invoice Store Key'});

        if ($shop->type != ShopTypeEnum::FULFILMENT) {
            if (!$this->auroraModelData->{'Invoice Order Key'} and $this->auroraModelData->{'Invoice Total Amount'} == 0) {
                // just ignore it
                return;
            }

            if (!$this->auroraModelData->{'Invoice Order Key'}) {
                // just ignore as well
                return;
            }


            $order = $this->parseOrder($this->organisation->id.':'.$this->auroraModelData->{'Invoice Order Key'}, forceTransactions: $forceWithTransactions);


            if (!$order) {
                $this->parsedData['parent'] = $this->parseCustomer($this->organisation->id.':'.$this->auroraModelData->{'Invoice Customer Key'});
            } else {
                $this->parsedData['parent'] = $order;
            }
        } else {
            $this->parsedData['parent'] = $this->parseCustomer($this->organisation->id.':'.$this->auroraModelData->{'Invoice Customer Key'});
        }


        $data = [];

        $data['foot_note'] = $this->auroraModelData->{'Invoice Message'};

        $billingAddressData = $this->parseAddress(prefix: 'Invoice', auAddressData: $this->auroraModelData);

        $date = $this->parseDate($this->auroraModelData->{'Invoice Date'});
        $date = new Carbon($date);

        $taxLiabilityAt = $this->parseDate($this->auroraModelData->{'Invoice Tax Liability Date'});
        if (!$taxLiabilityAt) {
            $taxLiabilityAt = $this->auroraModelData->{'Invoice Date'};
        }

        $taxCategory = $this->parseTaxCategory($this->auroraModelData->{'Invoice Tax Category Key'});

        $this->parsedData['invoice'] = [
            'reference'        => $this->auroraModelData->{'Invoice Public ID'},
            'type'             => strtolower($this->auroraModelData->{'Invoice Type'}),
            'created_at'       => $this->auroraModelData->{'Invoice Date'},
            'date'             => $this->auroraModelData->{'Invoice Date'},
            'tax_liability_at' => $taxLiabilityAt,
            'exchange'         => $this->auroraModelData->{'Invoice Currency Exchange'},

            'org_exchange' => GetHistoricCurrencyExchange::run($this->parsedData['parent']->shop->currency, $this->parsedData['parent']->organisation->currency, $date),
            'grp_exchange' => GetHistoricCurrencyExchange::run($this->parsedData['parent']->shop->currency, $this->parsedData['parent']->group->currency, $date),


            'gross_amount'     => $this->auroraModelData->{'Invoice Items Gross Amount'},
            'goods_amount'     => $this->auroraModelData->{'Invoice Items Net Amount'},
            'shipping_amount'  => $this->auroraModelData->{'Invoice Shipping Net Amount'},
            'charges_amount'   => $this->auroraModelData->{'Invoice Charges Net Amount'},
            'insurance_amount' => $this->auroraModelData->{'Invoice Insurance Net Amount'},

            'net_amount' => $this->auroraModelData->{'Invoice Total Net Amount'},
            'tax_amount' => $this->auroraModelData->{'Invoice Total Tax Amount'},

            'total_amount' => $this->auroraModelData->{'Invoice Total Amount'},


            'source_id'       => $this->organisation->id.':'.$this->auroraModelData->{'Invoice Key'},
            'data'            => $data,
            'billing_address' => new Address($billingAddressData),
            'currency_id'     => $this->parseCurrencyID($this->auroraModelData->{'Invoice Currency'}),
            'tax_category_id' => $taxCategory->id,

        ];
    }


    protected function fetchData($id): object|null
    {
        return DB::connection('aurora')
            ->table('Invoice Dimension')
            ->where('Invoice Key', $id)->first();
    }

    public function fetchInvoice(int $id, bool $forceWithTransactions = true): ?array
    {
        $this->auroraModelData = $this->fetchData($id);

        if ($this->auroraModelData) {
            $this->parseInvoiceModel($forceWithTransactions);
        }

        return $this->parsedData;
    }

}
