<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 16 Feb 2025 12:12:36 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Enums\Accounting\InvoiceCategory\InvoiceCategoryStateEnum;
use App\Enums\Accounting\InvoiceCategory\InvoiceCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FetchAuroraInvoiceCategory extends FetchAurora
{
    /**
     * @throws \Laravel\Octane\Exceptions\DdException
     */
    protected function parseModel(): void
    {
        if (!$this->auroraModelData->{'Invoice Category Key'}) {
            return;
        }

        $code = $this->auroraModelData->{'Category Code'};
        $code = preg_replace('/\s+/', '-', $code);

        $sourceSlug = Str::kebab(strtolower($code));

        $settings = [
        ];
        $type     = null;
        switch ($this->auroraModelData->{'Invoice Category Function Code'}) {
            case 'store':
                $type = InvoiceCategoryTypeEnum::SHOP_FALLBACK;

                $shop = $this->parseShop($this->organisation->id.':'.$this->auroraModelData->{'Invoice Category Function Argument'});
                data_set($settings, 'shop_id', $shop->id);

                break;
            case 'store_type':
                $type = InvoiceCategoryTypeEnum::SHOP_TYPE;
                if (str_contains($this->auroraModelData->{'Invoice Category Function Argument'}, 'Dropshipping')) {
                    $shopType = ShopTypeEnum::DROPSHIPPING;
                } else {
                    dd('A', $this->auroraModelData);
                }
                data_set($settings, 'shop_types', [$shopType]);
                break;
            case 'country':
            case 'not_in_country':
                $type = $this->auroraModelData->{'Invoice Category Function Code'} == 'country' ? InvoiceCategoryTypeEnum::IN_COUNTRY : InvoiceCategoryTypeEnum::NOT_IN_COUNTRY;

                $country_codes = explode(',', $this->auroraModelData->{'Invoice Category Function Argument'});
                $country_ids   = [];
                foreach ($country_codes as $country_code) {
                    $country_ids[] = $this->parseCountryID($country_code);
                }
                data_set($settings, 'country_ids', $country_ids);
                break;
            case 'in_level_type':
                if (str_contains($this->auroraModelData->{'Invoice Category Function Argument'}, 'Partner')) {
                    $type = InvoiceCategoryTypeEnum::IN_ORGANISATION;
                    data_set($settings, 'mode', 'any');
                } elseif (str_contains($this->auroraModelData->{'Invoice Category Function Argument'}, 'VIP')) {
                    $type = InvoiceCategoryTypeEnum::VIP;
                } else {
                    dd('A', $this->auroraModelData);
                }
                break;
            case 'external_invoicer':
                $type = InvoiceCategoryTypeEnum::EXTERNAL_INVOICER;
                break;
            case 'in_source':
                $type = InvoiceCategoryTypeEnum::IN_SALES_CHANNEL;

                $args = json_decode($this->auroraModelData->{'Invoice Category Function Argument'}, true);

                $salesChannelIds = [];
                foreach ($args as $arg) {
                    $salesChannel = $this->parseSalesChannel($this->organisation->id.':'.$arg);
                    if (!$salesChannel) {
                        dd('Sales Channel not found', $arg);
                    }
                    $salesChannelIds[] = $salesChannel->id;
                }

                data_set($settings, 'sales_channel_ids', $salesChannelIds);
                break;
            case 'customers':
                $type             = InvoiceCategoryTypeEnum::IN_ORGANISATION;
                $organisation_ids = [];
                if ($this->auroraModelData->{'Invoice Category Function Argument'} == '[6]') {
                    $organisation_ids[] = Organisation::where('slug', 'aw')->first()->id;
                } elseif ($this->auroraModelData->{'Invoice Category Function Argument'} == '[4,5]') {
                    $organisation_ids[] = Organisation::where('slug', 'sk')->first()->id;
                    $organisation_ids[] = Organisation::where('slug', 'es')->first()->id;
                } else {
                    dd('A', $this->auroraModelData);
                }
                data_set($settings, 'mode', 'in_array');
                data_set($settings, 'organisation_ids', $organisation_ids);
                break;

            default:
                dd($this->auroraModelData);
        }

        if ($this->auroraModelData->{'Invoice Category Currency Code'} == '') {
            $currencyID = $this->organisation->currency_id;
        } else {
            $currencyID = $this->parseCurrencyID($this->auroraModelData->{'Invoice Category Currency Code'});
            if (!$currencyID) {
                dd('Currency not found', $this->auroraModelData->{'Invoice Category Currency Code'});
            }
        }


        $this->parsedData['invoice_category'] = [
            'code'               => $code,
            'name'               => $this->auroraModelData->{'Category Label'},
            'state'              => match ($this->auroraModelData->{'Invoice Category Status'}) {
                'InProcess' => InvoiceCategoryStateEnum::IN_PROCESS,
                'Normal' => InvoiceCategoryStateEnum::ACTIVE,
                'ClosingDown' => InvoiceCategoryStateEnum::COOLDOWN,
                default => InvoiceCategoryStateEnum::CLOSED
            },
            'source_id'          => $this->organisation->id.':'.$this->auroraModelData->{'Category Key'},
            'source_slug'        => $sourceSlug,
            'fetched_at'         => now(),
            'last_fetched_at'    => now(),
            'priority'           => $this->auroraModelData->{'Invoice Category Function Order'},
            'settings'           => $settings,
            'type'               => $type,
            'currency_id'        => $currencyID,
            'show_in_dashboards' => !($this->auroraModelData->{'Invoice Category Hide Dashboard'} == 'Yes'),
            'organisation_id'    => $this->organisation->id,

        ];


        if ($settings == []) {
            unset($this->parsedData['invoice_category']['settings']);
        }

        $createdAt = $this->parseDatetime($this->auroraModelData->{'Invoice Category Valid From'});
        if ($createdAt) {
            $this->parsedData['invoice_category']['created_at'] = $createdAt;
        }
    }


    protected function fetchData($id): object|null
    {
        return DB::connection('aurora')
            ->table('Category Dimension')
            ->leftJoin('Invoice Category Dimension', 'Invoice Category Key', 'Category Key')
            ->where('Category Key', $id)->first();
    }

}
