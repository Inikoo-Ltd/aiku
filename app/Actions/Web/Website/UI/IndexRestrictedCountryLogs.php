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

        foreach ($this->getElementGroups($website) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
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

    protected function getElementGroups(Website $website): array
    {
        $blockedCountries = array_keys($website->blocked_country_regions);

        $countsQuery = RestrictedCountryRegionLog::query()
            ->join('ip_geolocations', 'ip_geolocations.id', '=', 'restricted_country_region_logs.ip_geolocation_id');

        if (empty($blockedCountries)) {
            $countsQuery->whereRaw('1 = 0');
        } else {
            $countsQuery->whereIn('ip_geolocations.country', $blockedCountries);
        }

        $counts = $countsQuery
            ->selectRaw('CAST(restricted_country_region_logs.was_blocked AS int) as was_blocked, count(*) as total')
            ->groupBy('restricted_country_region_logs.was_blocked')
            ->pluck('total', 'was_blocked');

        return [
            'was_blocked' => [
                'label'    => __('Status'),
                'elements' => [
                    '1' => [__('Blocked'), (int) $counts->get(1, 0)],
                    '0' => [__('Allowed'), (int) $counts->get(0, 0)],
                ],
                'engine'    => function ($query, $elements) {
                    $query->whereIn('restricted_country_region_logs.was_blocked', array_map(fn ($element) => (int) $element === 1, $elements));
                },
            ],
        ];
    }

    public function tableStructure(Website $website, ?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($website, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            foreach ($this->getElementGroups($website) as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements']
                );
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
                ->column(key: 'last_request_at', label: __('Last Request'), canBeHidden: false, sortable: true, searchable: false, align: 'right')
                ->defaultSort('-last_request_at');
        };
    }

    public function jsonResponse(LengthAwarePaginator $logs): AnonymousResourceCollection
    {
        return RestrictedCountryLogResource::collection($logs);
    }
}
