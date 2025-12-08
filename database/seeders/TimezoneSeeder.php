<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Wed, 25 Aug 2021 23:19:04 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2021, Inikoo
 *  Version 4.0
 */

namespace Database\Seeders;

use App\Models\Helpers\Country;
use App\Models\Helpers\Timezone;
use CommerceGuys\Addressing\Country\CountryRepository;
use DateTime;
use DateTimeZone;
use Illuminate\Database\Seeder;

class TimezoneSeeder extends Seeder
{
    /**
     * @throws \Exception
     */
    public function run()
    {
        foreach (DateTimeZone::listIdentifiers() as $identifier) {
            $tz = new DateTimeZone($identifier);

            $tz_location = $tz->getLocation();

            $data = [];

            $country = Country::withTrashed()->where('code', $tz_location['country_code'])->first();

            $country_id = null;
            if ($country) {
                $country_id = $country->id;
            } else {
                $data['fail_country_code'] = $tz_location['country_code'];
            }

            Timezone::UpdateOrCreate(
                ['name' => $tz->getName()],
                [
                    'offset' => $tz->getOffset(new DateTime('now', new DateTimeZone('UTC'))),
                    'latitude' => $tz_location['latitude'],
                    'longitude' => $tz_location['longitude'],
                    'location' => ($tz_location['comments'] == '?' ? '' : $tz_location['comments']),
                    'data' => $data,
                    'country_id' => $country_id,
                ]
            );
        }
        foreach (DateTimeZone::listAbbreviations() as $abbreviation => $abbreviationData) {
            foreach ($abbreviationData as $timezoneData) {
                if ($timezone = Timezone::where('name', $timezoneData['timezone_id'])->first()) {
                    $data = $timezone->data;
                    $abbreviations = data_get($data, 'abbreviations', []);
                    $abbreviations[] = $abbreviation;
                    data_set($data, 'abbreviations', array_unique($abbreviations));
                    $timezone->data = $data;
                    $timezone->save();
                }
            }
        }
        $row = 1;
        $handle = fopen(__DIR__.'/datasets/countryData.csv', 'r');
        if ($handle !== false) {
            while (($data = fgetcsv($handle, 1000)) !== false) {
                if ($row > 1) {
                    if ($country = Country::withTrashed()->where('code', $data[1])->first()) {
                        if ($timezone = Timezone::where('name', $data[11])->first()) {
                            $country->timezone_id = $timezone->id;
                            $country->save();
                        } else {
                            echo "Timezone not found : >$data[11]<\n";
                        }
                    } else {
                        echo "Country not found : $data[1]\n";
                    }
                }

                $row++;
            }
            fclose($handle);
        }

        $countryRepository = new CountryRepository;
        $countryList = $countryRepository->getList('en-GB');
        foreach ($countryList as $countryCode => $countryName) {
            if ($country = Country::withTrashed()->where('code', $countryCode)->first()) {
                $_country = $countryRepository->get($countryCode);

                $timezones = [];
                foreach ($_country->getTimezones() as $timezoneName) {
                    if ($timezone = Timezone::where('name', $timezoneName)->first()) {
                        $timezones[$timezone->id] = $timezone->id;
                    }
                }

                $country->timezones()->sync($timezones);
            }
        }
    }
}
