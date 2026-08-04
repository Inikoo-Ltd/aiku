<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 02 Aug 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\UI;

use App\Enums\Search\WebsiteSearchSourceEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Helpers\WebsiteSearchLog;
use App\Models\Web\Website;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Spatie\QueryBuilder\AllowedFilter;

trait WithWebsiteSearchLogsTable
{
    protected function getSearchLogElementGroups(Website $website, ?Closure $constrain = null): array
    {
        $base = WebsiteSearchLog::where('website_id', $website->id);
        if ($constrain) {
            $constrain($base);
        }

        $deviceCounts = (clone $base)->whereNotNull('device')->selectRaw('device, count(*) as count')->groupBy('device')->pluck('count', 'device');
        $sourceCounts = (clone $base)->whereNotNull('source')->selectRaw('source, count(*) as count')->groupBy('source')->pluck('count', 'source');
        $sourceLabels = WebsiteSearchSourceEnum::labels();

        return [
            'clicked' => [
                'label'    => __('Click'),
                'elements' => [
                    'clicked'     => [__('Clicked'), (clone $base)->whereNotNull('clicked_at')->count()],
                    'not_clicked' => [__('Not clicked'), (clone $base)->whereNull('clicked_at')->count()],
                ],
                'engine'   => function ($query, $elements) {
                    if (count($elements) === 1) {
                        array_pop($elements) === 'clicked'
                            ? $query->whereNotNull('website_search_logs.clicked_at')
                            : $query->whereNull('website_search_logs.clicked_at');
                    }
                },
            ],
            'results' => [
                'label'    => __('Results'),
                'elements' => [
                    'with_results' => [__('With results'), (clone $base)->where('results_count', '>', 0)->count()],
                    'no_results'   => [__('No results'), (clone $base)->where('results_count', 0)->count()],
                ],
                'engine'   => function ($query, $elements) {
                    if (count($elements) === 1) {
                        array_pop($elements) === 'with_results'
                            ? $query->where('website_search_logs.results_count', '>', 0)
                            : $query->where('website_search_logs.results_count', 0);
                    }
                },
            ],
            'logged'  => [
                'label'    => __('Visitor'),
                'elements' => [
                    'logged_in' => [__('Logged in'), (clone $base)->whereNotNull('web_user_id')->count()],
                    'guest'     => [__('Guest'), (clone $base)->whereNull('web_user_id')->count()],
                ],
                'engine'   => function ($query, $elements) {
                    if (count($elements) === 1) {
                        array_pop($elements) === 'logged_in'
                            ? $query->whereNotNull('website_search_logs.web_user_id')
                            : $query->whereNull('website_search_logs.web_user_id');
                    }
                },
            ],
            'device'  => [
                'label'    => __('Device'),
                'elements' => $deviceCounts->mapWithKeys(fn ($count, $device) => [$device => [$device, $count]])->all(),
                'engine'   => function ($query, $elements) {
                    $query->whereIn('website_search_logs.device', $elements);
                },
            ],
            'source'  => [
                'label'    => __('Opened from'),
                'elements' => $sourceCounts->mapWithKeys(fn ($count, $source) => [$source => [Arr::get($sourceLabels, $source, $source), $count]])->all(),
                'engine'   => function ($query, $elements) {
                    $query->whereIn('website_search_logs.source', $elements);
                },
            ],
        ];
    }

    protected function websiteSearchLogsQuery(Website $website, ?Closure $constrain = null, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereRaw('website_search_logs.query ILIKE ?', ["%$value%"])
                    ->orWhereRaw('customers.name ILIKE ?', ["%$value%"]);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(WebsiteSearchLog::class)
            ->where('website_search_logs.website_id', $website->id)
            ->leftJoin('customers', 'customers.id', '=', 'website_search_logs.customer_id');

        if ($constrain) {
            $constrain($queryBuilder);
        }

        foreach ($this->getSearchLogElementGroups($website, $constrain) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }

        return $queryBuilder
            ->defaultSort('-created_at')
            ->select([
                'website_search_logs.id',
                'website_search_logs.query',
                'website_search_logs.scope',
                'website_search_logs.source',
                'website_search_logs.device',
                'website_search_logs.browser',
                'website_search_logs.results_count',
                'website_search_logs.clicked_at',
                'website_search_logs.clicked_url',
                'website_search_logs.created_at',
                'customers.name as customer_name',
                'customers.slug as customer_slug',
            ])
            ->allowedSorts(['query', 'scope', 'source', 'device', 'results_count', 'clicked_at', 'created_at', 'customer_name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    /**
     * @param array<int, string> $exceptColumns columns that are constant on drill-down pages
     */
    protected function websiteSearchLogsTableStructure(Website $website, ?Closure $constrain = null, array $exceptColumns = [], $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($website, $constrain, $exceptColumns, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            foreach ($this->getSearchLogElementGroups($website, $constrain) as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements']
                );
            }

            $table
                ->withGlobalSearch()
                ->withTitle(title: __('Searches'))
                ->withLabelRecord([__('search'), __('searches')])
                ->column(key: 'created_at', label: __('Date'), canBeHidden: false, sortable: true);

            if (!in_array('query', $exceptColumns)) {
                $table->column(key: 'query', label: __('Query'), canBeHidden: false, sortable: true, searchable: true);
            }

            $table->column(key: 'scope', label: __('Section'), canBeHidden: false, sortable: true);

            if (!in_array('customer_name', $exceptColumns)) {
                $table->column(key: 'customer_name', label: __('Customer'), canBeHidden: false, sortable: true, searchable: true);
            }

            $table
                ->column(key: 'source', label: __('Opened from'), canBeHidden: false, sortable: true)
                ->column(key: 'device', label: __('Device'), canBeHidden: false, sortable: true)
                ->column(key: 'results_count', label: __('Results'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'clicked_at', label: __('Clicked'), canBeHidden: false, sortable: true)
                ->defaultSort('-created_at');
        };
    }
}
