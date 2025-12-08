<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:10 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Models\Helpers\Country;
use App\Models\Helpers\Language;
use App\Models\SupplyChain\Agent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FetchAuroraAgent extends FetchAurora
{
    use WithAuroraImages;

    protected function parseModel(): void
    {
        $phone = $this->auroraModelData->{'Agent Main Plain Mobile'};
        if ($phone == '') {
            $phone = $this->auroraModelData->{'Agent Main Plain Telephone'};
        }

        $currency_id = $this->parseCurrencyID($this->auroraModelData->{'Agent Default Currency Code'});
        $country_id = $this->parseCountryID($this->auroraModelData->{'Agent Products Origin Country Code'});
        $country = Country::find($country_id);

        $timezone_id = $country->timezones()->first()->id;
        $language_id = Language::where('code', 'en-gb')->first()->id;

        $code = strtolower($this->auroraModelData->{'Agent Code'});

        $code = preg_replace('/\s/', '', $code);
        $code = preg_replace('/^aw/', '', $code);

        if ($code == 'zesttex' or $code == 'AWZesttex') {
            $agent = Agent::where('code', 'india')->firstOrFail();

            $this->parsedData['agent']['source_id'] = $this->organisation->id.':'.$this->auroraModelData->{'Agent Key'};
            $this->parsedData['foundAgent'] = $agent;

            return;
        }

        $this->parsedData['agent'] =
            [
                'code' => $code,
                'name' => $this->auroraModelData->{'Agent Name'},
                'contact_name' => $this->auroraModelData->{'Agent Main Contact Name'},
                'country_id' => $country_id,
                'timezone_id' => $timezone_id,
                'language_id' => $language_id,
                'currency_id' => $currency_id,
                'email' => $this->auroraModelData->{'Agent Main Plain Email'},
                'phone' => $phone,
                'source_id' => $this->organisation->id.':'.$this->auroraModelData->{'Agent Key'},
                'source_slug' => Str::kebab(strtolower($this->auroraModelData->{'Agent Code'})),
                'created_at' => $this->auroraModelData->{'Agent Valid From'},
                'address' => $this->parseAddress(prefix: 'Agent Contact', auAddressData: $this->auroraModelData),
                'fetched_at' => now(),
                'last_fetched_at' => now(),

            ];
        $this->parsePhoto();
    }

    private function parsePhoto(): void
    {
        $profile_images = $this->getModelImagesCollection(
            'Agent',
            $this->auroraModelData->{'Agent Key'}
        )->map(function ($auroraImage) {
            return $this->fetchImage($auroraImage);
        });
        $this->parsedData['photo'] = $profile_images->toArray();
    }

    protected function fetchData($id): ?object
    {
        return DB::connection('aurora')
            ->table('Agent Dimension')
            ->where('Agent Key', $id)->first();
    }
}
