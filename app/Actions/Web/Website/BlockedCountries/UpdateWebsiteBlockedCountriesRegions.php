<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Jun 2026 23:19:51 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\BlockedCountries;

use App\Actions\Helpers\ClearCacheByWildcard;
use App\Actions\OrgAction;
use App\Models\Web\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateWebsiteBlockedCountriesRegions extends OrgAction
{
    use AsAction;

    public function handle(Website $website, array $modelData, bool $countyOnly = false): Website
    {
        $currentBlockedCountries = $website->blocked_country_regions;
        $countryCode             = strtoupper($modelData['country']);


        if ($countyOnly) {
            $currentBlockedCountries[$countryCode] = [];
        } elseif ($this->hasRestrictions($modelData)) {
            $countryRestriction                    = $this->preProcessCountryRestrictions($modelData);
            $currentBlockedCountries[$countryCode] = $countryRestriction;
        } else {
            unset($currentBlockedCountries[$countryCode]);
        }


        $website->update(['blocked_country_regions' => $currentBlockedCountries]);

        ClearCacheByWildcard::run("website-geo-blocked-ips:$website->id:$countryCode:*");
        $key = config('iris.cache.website.prefix').'_domain:'.$website->domain;
        Cache::forget($key);

        return $website;
    }

    public function hasRestrictions(array $modelData): bool
    {
        if (!Arr::get($modelData, 'city') && !Arr::get($modelData, 'postcode')) {
            return false;
        }

        return true;
    }

    public function preProcessCountryRestrictions(array $modelData): ?array
    {
        $countryRestriction = [];
        if (Arr::get($modelData, 'city')) {
            $countryRestriction['cities'] = Arr::get($modelData, 'city');
        }
        if (Arr::get($modelData, 'postcode')) {
            $countryRestriction['postcode'] = Arr::get($modelData, 'postcode');
        }

        return $countryRestriction;
    }

    public function rules(): array
    {
        return [
            'country'  => ['required', 'string', 'size:2', 'exists:countries,code'],
            'city'     => [
                'sometimes',
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    if (@preg_match($value, '') === false) {
                        $fail("The $attribute must be a valid regular expression.");
                    }
                }
            ],
            'postcode' => [
                'sometimes',
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    if (@preg_match($value, '') === false) {
                        $fail("The $attribute must be a valid regular expression.");
                    }
                }
            ]
        ];
    }

    public function action(Website $website, array $modelData): Website
    {
        $this->asAction = true;
        $this->initialisationFromShop($website->shop, $modelData);

        return $this->handle($website, $this->validatedData);
    }

    public function getCommandSignature(): string
    {
        return 'website:blocked-countries-regions:update {website} {country} {--city=} {--postcode=}';
    }

    public function asCommand(Command $command): int
    {
        $website = Website::where('slug', $command->argument('website'))->firstOrFail();


        $modelData = [
            'country'  => $command->argument('country'),
            'city'     => $command->option('city'),
            'postcode' => $command->option('postcode'),
        ];

        UpdateWebsiteBlockedCountriesRegions::make()->action($website, $modelData);

        $command->info('Website blocked countries regions updated successfully');

        return 0;
    }

}
