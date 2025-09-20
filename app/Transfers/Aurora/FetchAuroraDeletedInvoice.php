<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:10 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Actions\Helpers\CurrencyExchange\GetHistoricCurrencyExchange;
use App\Models\Helpers\Address;
use Illuminate\Support\Facades\DB;

class FetchAuroraDeletedInvoice extends FetchAurora
{
    protected function parseModel(): void
    {
        $auroraDeletedData = json_decode($this->auroraModelData->{'Invoice Deleted Metadata'});

        if ($this->auroraModelData->{'Invoice Deleted Order Key'} && $order = $this->parseOrder($this->organisation->id.':'.$this->auroraModelData->{'Invoice Deleted Order Key'})) {
            $this->parsedData['parent'] = $order;
            $currencyID                 = $order->currency_id;
        } elseif ($auroraDeletedData->{'Invoice Customer Key'}) {
            $customer = $this->parseCustomer($this->organisation->id.':'.$auroraDeletedData->{'Invoice Customer Key'});
            if (!$customer) {
                return;
            }
            $this->parsedData['parent'] = $customer;
            $currencyID                 = $customer->shop->currency_id;
        } else {
            return;
        }


        $deleted_at = $this->auroraModelData->{'Invoice Deleted Date'};
        if ($deleted_at == '0000-00-00 00:00:00') {
            $deleted_at = $auroraDeletedData->{'Invoice Date'};
        }

        if (!$deleted_at) {
            print "Deleted invoice no date\n";

            return;
        }

        $items = [];
        if (isset($auroraDeletedData->items)) {
            $items = $auroraDeletedData->items;
            if (!$items) {
                $items = [];
            }
        }


        $data = [
            'deleted' => [
                'legacy' => [
                    'items' => $items
                ]
            ]
        ];

        $billingAddressData = $this->parseAddress(prefix: 'Invoice', auAddressData: $auroraDeletedData);

        if (!$billingAddressData['country_id']) {
            $billingAddressData['country_id'] = $this->parsedData['parent']->shop->country_id;
        }


        $billingAddress = new Address($billingAddressData);

        $date = $this->parseDatetime($auroraDeletedData->{'Invoice Date'});

        $taxLiabilityAt = $this->parseDatetime($auroraDeletedData->{'Invoice Tax Liability Date'});
        if (!$taxLiabilityAt) {
            $taxLiabilityAt = $date;
        }

        $taxCategory = null;
        if (isset($auroraDeletedData->{'Invoice Tax Category Key'})) {
            $taxCategory = $this->parseTaxCategory($auroraDeletedData->{'Invoice Tax Category Key'});
        }


        $deletedBy = $this->parseUser($this->organisation->id.':'.$this->auroraModelData->{'Invoice Deleted User Key'});

        $this->parsedData['invoice'] =
            [
                'reference'        => $this->auroraModelData->{'Invoice Deleted Public ID'},
                'type'             => strtolower($this->auroraModelData->{'Invoice Deleted Type'}),
                'created_at'       => $date,
                'date'             => $date,
                'tax_liability_at' => $taxLiabilityAt,
                'org_exchange'     => GetHistoricCurrencyExchange::run($this->parsedData['parent']->shop->currency, $this->parsedData['parent']->organisation->currency, $date),
                'grp_exchange'     => GetHistoricCurrencyExchange::run($this->parsedData['parent']->shop->currency, $this->parsedData['parent']->group->currency, $date),
                'gross_amount'     => $auroraDeletedData->{'Invoice Items Gross Amount'},
                'goods_amount'     => $auroraDeletedData->{'Invoice Items Net Amount'},
                'shipping_amount'  => $auroraDeletedData->{'Invoice Shipping Net Amount'},
                'charges_amount'   => $auroraDeletedData->{'Invoice Charges Net Amount'},
                'insurance_amount' => $auroraDeletedData->{'Invoice Insurance Net Amount'},
                'net_amount'       => $auroraDeletedData->{'Invoice Total Net Amount'},
                'tax_amount'       => $auroraDeletedData->{'Invoice Total Tax Amount'},
                'total_amount'     => $auroraDeletedData->{'Invoice Total Amount'},
                'source_id'        => $this->organisation->id.':'.$this->auroraModelData->{'Invoice Deleted Key'},
                'data'             => $data,
                'billing_address'  => $billingAddress,
                'currency_id'      => $currencyID,

                'fetched_at'      => now(),
                'last_fetched_at' => now(),


                'deleted_at'                         => $deleted_at,
                'deleted_note'                       => $this->auroraModelData->{'Invoice Deleted Note'},
                'deleted_from_deleted_invoice_fetch' => true,
                'deleted_by'                         => $deletedBy?->id
            ];

        if ($taxCategory) {
            $this->parsedData['invoice']['tax_category_id'] = $taxCategory->id;
        }
    }


    protected function fetchData($id): object|null
    {
        return DB::connection('aurora')
            ->table('Invoice Deleted Dimension')
            ->where('Invoice Deleted Key', $id)->first();
    }
}
