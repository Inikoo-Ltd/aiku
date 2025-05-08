<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:09 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Actions\Transfers\Aurora\FetchAuroraWarehouses;
use App\Actions\Utils\Abbreviate;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Inventory\Warehouse;
use App\Models\Catalogue\Shop;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FetchAuroraShop extends FetchAurora
{
    public function fetch(int $id): ?array
    {
        $this->auroraModelData = $this->fetchData($id);

        if (!$this->auroraModelData) {
            return null;
        }

        if (strtolower($this->auroraModelData->{'Store Type'}) == 'fulfilment') {
            $this->parsedData['shop'] = [
                'fetched_at'      => now(),
                'last_fetched_at' => now(),
                'source_id'       => $this->organisation->id.':'.$this->auroraModelData->{'Store Key'},

            ];

            $auroraSettings = json_decode($this->auroraModelData->{'Store Settings'}, true);


            $this->parsedData['tax_number'] = $this->parseTaxNumber(
                number: $this->auroraModelData->{'Store VAT Number'},
                countryID: $this->parseCountryID($auroraSettings['tax_country_code'])
            );

            return $this->parsedData;
        }

        $code     = strtoupper($this->auroraModelData->{'Store Code'});
        $sourceId = $this->organisation->id.':'.$this->auroraModelData->{'Store Key'};
        if (Shop::where('code', $code)->whereNot('source_id', $sourceId)->exists()) {
            $code = $code.strtoupper(Abbreviate::run(string: $this->organisation->slug, maximumLength: 2));
        }
        $this->auroraModelData->code = $code;
        $this->parseModel();

        return $this->parsedData;
    }

    protected function parseModel(): void
    {
        $type = match (strtolower($this->auroraModelData->{'Store Type'})) {
            'b2b' => ShopTypeEnum::B2B,
            'b2c' => ShopTypeEnum::B2C,
            'fulfilment' => ShopTypeEnum::FULFILMENT,
            'dropshipping' => ShopTypeEnum::DROPSHIPPING,
        };

        if ($type == ShopTypeEnum::FULFILMENT) {
            return;
        }


        $masterShopId = null;


        $this->parsedData['source_department_key'] = $this->auroraModelData->{'Store Department Category Key'};
        $this->parsedData['source_family_key']     = $this->auroraModelData->{'Store Family Category Key'};


        $auroraSettings = json_decode($this->auroraModelData->{'Store Settings'}, true);


        $this->parsedData['tax_number'] = $this->parseTaxNumber(
            number: $this->auroraModelData->{'Store VAT Number'},
            countryID: $this->parseCountryID($auroraSettings['tax_country_code'])
        );

        if ($this->auroraModelData->code == 'AROM') {
            $this->auroraModelData->code = 'AROMA';
        }


        //        if ($type == ShopTypeEnum::FULFILMENT) {
        //            $masterShopId = $this->organisation->group->masterShops()->where('type', ShopTypeEnum::FULFILMENT)->first()->id;
        //        } else

        if ($type == ShopTypeEnum::DROPSHIPPING) {
            if ($this->auroraModelData->code != 'DS') {
                $masterShopId = $this->organisation->group->masterShops()->where('type', ShopTypeEnum::DROPSHIPPING)->first()->id;
            }
        } elseif ($type == ShopTypeEnum::B2B) {
            if (in_array($this->auroraModelData->code, ['AWAD', 'AC', 'ACE', 'ACAR'])) {
                $masterShopId = $this->organisation->group->masterShops()->where('type', ShopTypeEnum::B2B)->where('code', 'ac')->first()->id;
            } elseif ($this->auroraModelData->code == 'AROMA') {
                $masterShopId = $this->organisation->group->masterShops()->where('type', ShopTypeEnum::B2B)->where('code', 'aroma')->first()->id;
            } elseif (!in_array($this->auroraModelData->code, ['HA', 'AB', 'AWT', 'HH'])) {
                $masterShopId = $this->organisation->group->masterShops()->where('type', ShopTypeEnum::B2B)->first()->id;
            }
        }

        $state = Str::snake($this->auroraModelData->{'Store Status'} == 'Normal' ? 'Open' : $this->auroraModelData->{'Store Status'}, '-');
        $state = match ($state) {
            'in_process' => ShopStateEnum::IN_PROCESS,
            'open' => ShopStateEnum::OPEN,
            'closing_down' => ShopStateEnum::CLOSING_DOWN,
            'closed' => ShopStateEnum::CLOSED,
        };

        $settings = [
            'can_collect'  => $this->auroraModelData->{'Store Can Collect'} === 'Yes',
            'address_link' => 'Organisation:default'
        ];

        $settings['collect_address_link'] = 'Organisation:default';

        $registrationNeedsApproval = false;

        $auroraWebsiteData = DB::connection('aurora')
            ->table('Website Dimension')
            ->where('Website Key', $this->auroraModelData->{'Store Website Key'})->first();


        if ($auroraWebsiteData && $auroraWebsiteData->{'Website Registration Type'} == 'ApprovedOnly') {
            $registrationNeedsApproval = true;
        }

        if ($this->auroraModelData->{'Store Type'} == 'Fulfilment') {
            $registrationNeedsApproval = true;
        }


        $this->parsedData['shop'] = [
            'master_shop_id' => $masterShopId,
            'code'           => $this->auroraModelData->code,
            'name'           => $this->auroraModelData->{'Store Name'},
            'company_name'   => $this->auroraModelData->{'Store Company Name'},
            'contact_name'   => $this->auroraModelData->{'Store Contact Name'},


            'email' => $this->auroraModelData->{'Store Email'},
            'phone' => $this->auroraModelData->{'Store Telephone'},

            'identity_document_number' => $this->auroraModelData->{'Store Company Number'},
            'state'                    => $state,

            'type' => $type,

            'country_id'                  => $this->parseCountryID($this->auroraModelData->{'Store Home Country Code 2 Alpha'}),
            'language_id'                 => $this->parseLanguageID($this->auroraModelData->{'Store Locale'}),
            'currency_id'                 => $this->parseCurrencyID($this->auroraModelData->{'Store Currency Code'}),
            'timezone_id'                 => $this->parseTimezoneID($this->auroraModelData->{'Store Timezone'}),
            'open_at'                     => $this->parseDatetime($this->auroraModelData->{'Store Valid From'}),
            'closed_at'                   => $this->parseDatetime($this->auroraModelData->{'Store Valid To'}),
            'created_at'                  => $this->parseDatetime($this->auroraModelData->{'Store Valid From'}),
            'source_id'                   => $this->organisation->id.':'.$this->auroraModelData->{'Store Key'},
            'settings'                    => $settings,
            'fetched_at'                  => now(),
            'last_fetched_at'             => now(),
            'registration_needs_approval' => $registrationNeedsApproval

        ];

        if ($this->auroraModelData->{'Store Invoice Message'}) {
            $this->parsedData['shop']['invoice_footer'] = $this->auroraModelData->{'Store Invoice Message'};
        }


        if ($type == ShopTypeEnum::FULFILMENT) {
            foreach (
                DB::connection('aurora')
                    ->table('Warehouse Dimension')
                    ->select('Warehouse Key as source_id')
                    ->orderBy('source_id')->get() as $shopData
            ) {
                FetchAuroraWarehouses::run($this->organisationSource, $shopData->source_id);
            }
            $this->organisation->refresh();

            /** @var Warehouse $warehouse */
            $warehouse                              = $this->organisation->warehouses()->first();
            $this->parsedData['shop']['warehouses'] = [$warehouse->id];
        }
    }


    protected function fetchData($id): object|null
    {
        return DB::connection('aurora')
            ->table('Store Dimension')
            ->where('Store Key', $id)->first();
    }
}
