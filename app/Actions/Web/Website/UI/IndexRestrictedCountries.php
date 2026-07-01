<?php

/*
 * Author: stewicca <wiccaalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Website\UI;

use App\Actions\OrgAction;
use App\Http\Resources\Web\RestrictedCountryResource;
use App\InertiaTable\InertiaTable;
use App\Models\Web\IpGeolocation;
use App\Models\Web\Website;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Spatie\QueryBuilder\AllowedFilter;

class IndexRestrictedCountries extends OrgAction
{
    public function handle(Website $website, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('ip_geolocations.country', $value)
                    ->orWhereStartWith('ip_geolocations.city', $value)
                    ->orWhereStartWith('ip_geolocations.postcode', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $regions      = $website->blocked_country_regions;
        $queryBuilder = QueryBuilder::for(IpGeolocation::class);

        if (empty($regions)) {
            $queryBuilder->whereRaw('1 = 0');
        } else {
            $queryBuilder->where(function ($query) use ($regions) {
                foreach ($regions as $country => $regionData) {
                    $postcode = $this->pcreToPosix(Arr::get($regionData, 'postcode'));
                    $city     = $this->pcreToPosix(Arr::get($regionData, 'cities'));

                    $query->orWhere(function ($scope) use ($country, $postcode, $city) {
                        $scope->where('ip_geolocations.country', $country)
                            ->where(function ($regex) use ($postcode, $city) {
                                if ($postcode) {
                                    $regex->orWhereRaw('ip_geolocations.postcode ~ ?', [$postcode]);
                                }
                                if ($city) {
                                    $regex->orWhereRaw('ip_geolocations.city ~ ?', [$city]);
                                }
                            });
                    });
                }
            });
        }

        return $queryBuilder
            ->defaultSort('country')
            ->select(['ip_geolocations.id', 'ip_geolocations.country', 'ip_geolocations.city', 'ip_geolocations.postcode', 'ip_geolocations.ip'])
            ->allowedSorts(['country', 'city', 'postcode'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()?->getName())
            ->withQueryString();
    }

    public function tableStructure(?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState([
                    'title'       => __('No restricted countries'),
                    'description' => __('No geolocations match the blocked regions for this website yet'),
                ])
                ->column(key: 'country', label: __('Country'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'city', label: __('City'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'postcode', label: __('Postcode'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'ip', label: __('IP'), canBeHidden: false, sortable: false, searchable: false)
                ->defaultSort('country');
        };
    }

    public function jsonResponse(LengthAwarePaginator $geolocations): AnonymousResourceCollection
    {
        return RestrictedCountryResource::collection($geolocations);
    }

    private function pcreToPosix(?string $pcre): ?string
    {
        if (!$pcre) {
            return null;
        }

        return preg_match('#^/(.*)/[a-zA-Z]*$#s', $pcre, $m) ? $m[1] : $pcre;
    }
}
