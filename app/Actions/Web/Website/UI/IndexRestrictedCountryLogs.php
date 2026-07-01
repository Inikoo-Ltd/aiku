<?php

/*
 * Author: stewicca <wiccaalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Website\UI;

use App\Actions\OrgAction;
use App\Http\Resources\Web\RestrictedCountryLogResource;
use App\InertiaTable\InertiaTable;
use App\Models\Web\RestrictedCountryRegionLog;
use App\Models\Web\Website;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;

class IndexRestrictedCountryLogs extends OrgAction
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

        $blockedCountries = array_keys($website->blocked_country_regions);

        $queryBuilder = QueryBuilder::for(RestrictedCountryRegionLog::class)
            ->join('ip_geolocations', 'ip_geolocations.id', '=', 'restricted_country_region_logs.ip_geolocation_id');

        if (empty($blockedCountries)) {
            $queryBuilder->whereRaw('1 = 0');
        } else {
            $queryBuilder->whereIn('ip_geolocations.country', $blockedCountries);
        }

        return $queryBuilder
            ->defaultSort('-last_request_at')
            ->select([
                'restricted_country_region_logs.id',
                'ip_geolocations.country',
                'ip_geolocations.city',
                'ip_geolocations.postcode',
                'ip_geolocations.ip',
                'restricted_country_region_logs.was_blocked',
                'restricted_country_region_logs.number_requests',
                'restricted_country_region_logs.last_request_at',
            ])
            ->allowedSorts(['country', 'city', 'postcode', 'was_blocked', 'number_requests', 'last_request_at'])
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
                    'title'       => __('No logs'),
                    'description' => __('No restricted-region requests logged for this website yet'),
                ])
                ->column(key: 'status', label: '', canBeHidden: false, sortable: false, searchable: false)
                ->column(key: 'country', label: __('Country'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'city', label: __('City'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'postcode', label: __('Postcode'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'ip', label: __('IP'), canBeHidden: false, sortable: false, searchable: false)
                ->column(key: 'number_requests', label: __('Requests'), canBeHidden: false, sortable: true, searchable: false)
                ->column(key: 'last_request_at', label: __('Last request'), canBeHidden: false, sortable: true, searchable: false, type: 'date')
                ->defaultSort('-last_request_at');
        };
    }

    public function jsonResponse(LengthAwarePaginator $logs): AnonymousResourceCollection
    {
        return RestrictedCountryLogResource::collection($logs);
    }
}
