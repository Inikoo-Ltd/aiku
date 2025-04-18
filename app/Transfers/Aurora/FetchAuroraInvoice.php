<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:10 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Actions\Helpers\CurrencyExchange\GetHistoricCurrencyExchange;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Ordering\SalesChannel\SalesChannelTypeEnum;
use App\Models\Helpers\Address;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FetchAuroraInvoice extends FetchAurora
{
    protected function parseInvoiceModel(): void
    {
        $shop = $this->parseShop($this->organisation->id.':'.$this->auroraModelData->{'Invoice Store Key'});

        if ($shop->type != ShopTypeEnum::FULFILMENT) {
            //            if (!$this->auroraModelData->{'Invoice Order Key'} && $this->auroraModelData->{'Invoice Total Amount'} == 0) {
            //                print ">>>No Invoice Order Key and no total \n";
            //
            //                // just ignore it
            //                return;
            //            }

            //            if (!$this->auroraModelData->{'Invoice Order Key'}) {
            //                print "No Invoice Order Key\n";
            //                // just ignore as well
            //                return;
            //            }
        }
        $this->parsedData['parent'] = $this->parseCustomer($this->organisation->id.':'.$this->auroraModelData->{'Invoice Customer Key'});


        $order = $this->parseOrder($this->organisation->id.':'.$this->auroraModelData->{'Invoice Order Key'});


        $data = [];

        $data['foot_note'] = $this->auroraModelData->{'Invoice Message'};

        $billingAddressData = $this->parseAddress(prefix: 'Invoice', auAddressData: $this->auroraModelData);

        if ($billingAddressData['country_id'] === null) {
            $billingAddressData['country_id'] = $shop->country_id;
        }


        $date = $this->parseDatetime($this->auroraModelData->{'Invoice Date'});
        $date = new Carbon($date);

        $taxLiabilityAt = $this->parseDatetime($this->auroraModelData->{'Invoice Tax Liability Date'});
        if (!$taxLiabilityAt) {
            $taxLiabilityAt = $this->auroraModelData->{'Invoice Date'};
        }

        $taxCategory = $this->parseTaxCategory($this->auroraModelData->{'Invoice Tax Category Key'});


        $salesChannel = null;

        if ($shop->type == ShopTypeEnum::FULFILMENT) {
            $salesChannel = $shop->group->salesChannels()->where('type', SalesChannelTypeEnum::NA)->first();
        } elseif ($this->auroraModelData->{'Invoice Source Key'}) {
            $salesChannel = $this->parseSalesChannel($this->organisation->id.':'.$this->auroraModelData->{'Invoice Source Key'});
        }

        $metadata = $this->auroraModelData->{'Invoice Metadata'};
        if ($metadata) {
            $metadata = json_decode($metadata, true);
        } else {
            $metadata = [];
        }

        $footer = Arr::get($metadata, 'store_message', '');
        if ($footer === null) {
            $footer = '';
        }

        $isVip = $this->auroraModelData->{'Invoice Customer Level Type'} == 'VIP';

        $AsOrganisation = null;
        if ($this->auroraModelData->{'Invoice Customer Level Type'} == 'Partner') {
            if (in_array($this->auroraModelData->{'Invoice Customer Name'}, ['Ancient Wisdom Marketing Ltd', 'Ancient Wisdom', 'Ancient Wisdom Marketing Ltd.'])) {
                $AsOrganisation = Organisation::where('slug', 'aw')->first();
            } elseif ($this->auroraModelData->{'Invoice Customer Name'} == 'Ancient Wisdom s.r.o.') {
                $AsOrganisation = Organisation::where('slug', 'sk')->first();
            } elseif (in_array($this->auroraModelData->{'Invoice Customer Name'}, ['AW Artisan S.L', 'AW Artisan S. L', 'AW-REGALOS SL', 'AW Artisan S.L.'])) {
                $AsOrganisation = Organisation::where('slug', 'es')->first();
            } elseif (in_array($this->auroraModelData->{'Invoice Customer Name'}, ['AW Aromatics Ltd', 'AW Aromatics'])) {
                $AsOrganisation = Organisation::where('slug', 'aroma')->first();
            } elseif (in_array($this->auroraModelData->{'Invoice Customer Name'}, ['aw China', 'Yiwu Saikun Import And EXPORT CO., Ltd'])) {
                $AsOrganisation = Organisation::where('slug', 'china')->first();
            }

            if (in_array($this->auroraModelData->{'Invoice Customer Key'}, [
                10362,
                17032,
                392469
            ])) {
                $isVip = true;
            }
        }


        $asEmployeeID = null;

        $externalInvoicer = null;
        if ($this->auroraModelData->{'Invoice External Invoicer Key'} == 1) {
            $externalInvoicer = 2;
        }

        $type = strtolower($this->auroraModelData->{'Invoice Type'});

        $originalInvoiceId = null;
        if ($type == 'refund') {

            if ($order) {
                $invoice           = $order->invoices()->where('invoices.type', InvoiceTypeEnum::INVOICE)->first();
                if ($invoice) {
                    $originalInvoiceId = $invoice->id;
                }

            }
        }


        $this->parsedData['invoice'] = [
            'reference'        => $this->auroraModelData->{'Invoice Public ID'},
            'type'             => $type,
            'created_at'       => $this->auroraModelData->{'Invoice Date'},
            'date'             => $this->auroraModelData->{'Invoice Date'},
            'tax_liability_at' => $taxLiabilityAt,

            'org_exchange' => GetHistoricCurrencyExchange::run($this->parsedData['parent']->shop->currency, $this->parsedData['parent']->organisation->currency, $date),
            'grp_exchange' => GetHistoricCurrencyExchange::run($this->parsedData['parent']->shop->currency, $this->parsedData['parent']->group->currency, $date),


            'gross_amount'     => $this->auroraModelData->{'Invoice Items Gross Amount'},
            'goods_amount'     => $this->auroraModelData->{'Invoice Items Net Amount'},
            'shipping_amount'  => $this->auroraModelData->{'Invoice Shipping Net Amount'},
            'charges_amount'   => $this->auroraModelData->{'Invoice Charges Net Amount'},
            'insurance_amount' => $this->auroraModelData->{'Invoice Insurance Net Amount'},

            'net_amount'           => $this->auroraModelData->{'Invoice Total Net Amount'},
            'tax_amount'           => $this->auroraModelData->{'Invoice Total Tax Amount'},
            'total_amount'         => $this->auroraModelData->{'Invoice Total Amount'},
            'source_id'            => $this->organisation->id.':'.$this->auroraModelData->{'Invoice Key'},
            'data'                 => $data,
            'billing_address'      => new Address($billingAddressData),
            'currency_id'          => $this->parseCurrencyID($this->auroraModelData->{'Invoice Currency'}),
            'tax_category_id'      => $taxCategory->id,
            'fetched_at'           => now(),
            'last_fetched_at'      => now(),
            'footer'               => $footer,
            'invoice_category_id'  => $this->parseInvoiceCategory($this->organisation->id.':'.$this->auroraModelData->{'Invoice Category Key'})?->id,
            'is_vip'               => $isVip,
            'as_organisation_id'   => $AsOrganisation?->id,
            'as_employee_id'       => $asEmployeeID,
            'external_invoicer_id' => $externalInvoicer,
            'original_invoice_id'  => $originalInvoiceId,

        ];
        if ($order) {
            $this->parsedData['invoice']['order_id'] = $order->id;
        }



        if ($this->auroraModelData->{'Invoice Category Key'}) {
            $invoiceCategory = $this->parseInvoiceCategory($this->organisation->id.':'.$this->auroraModelData->{'Invoice Category Key'});
            if ($invoiceCategory) {
                $this->parsedData['invoice']['invoice_category_id'] = $invoiceCategory->id;
            }
        }

        if ($salesChannel) {
            $this->parsedData['invoice']['sales_channel_id'] = $salesChannel->id;
        }
    }


    protected function fetchData($id): object|null
    {
        return DB::connection('aurora')
            ->table('Invoice Dimension')
            ->where('Invoice Key', $id)->first();
    }

    public function fetchInvoice(int $id): ?array
    {
        $this->auroraModelData = $this->fetchData($id);

        if ($this->auroraModelData) {
            $this->parseInvoiceModel();
        }

        return $this->parsedData;
    }

}
