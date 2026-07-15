<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Jul 2026 03:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class SetupTypesenseSearchAnalytics
{
    use AsAction;

    public string $commandSignature = 'search:setup-analytics';

    public const string POPULAR_QUERIES_COLLECTION = 'popular_searches';
    public const string NO_HIT_QUERIES_COLLECTION = 'no_hit_searches';
    public const string PRODUCT_CLICK_EVENT = 'products_click_event';

    public function handle(): array
    {
        $base = $this->baseUrl();

        $results = [];
        foreach ([self::POPULAR_QUERIES_COLLECTION, self::NO_HIT_QUERIES_COLLECTION] as $collection) {
            $exists = $this->client()->get($base.'/collections/'.$collection)->successful();
            if (!$exists) {
                $response = $this->client()->post($base.'/collections', [
                    'name'   => $collection,
                    'fields' => [
                        ['name' => 'q', 'type' => 'string'],
                        ['name' => 'count', 'type' => 'int32'],
                    ],
                ]);
                $results["collection $collection"] = $response->status();
            } else {
                $results["collection $collection"] = 'exists';
            }
        }

        $sourceCollections = collect($this->client()->get($base.'/collections')->json())
            ->pluck('name')
            ->reject(fn (string $name) => in_array($name, [self::POPULAR_QUERIES_COLLECTION, self::NO_HIT_QUERIES_COLLECTION], true))
            ->values()
            ->all();

        $rules = [];
        foreach ($sourceCollections as $collection) {
            $rules['popular_queries_'.$collection] = [
                'type'       => 'popular_queries',
                'collection' => $collection,
                'event_type' => 'search',
                'rule_tag'   => 'popular_searches',
                'params'     => [
                    'destination_collection'  => self::POPULAR_QUERIES_COLLECTION,
                    'limit'                   => 1000,
                    'capture_search_requests' => true,
                ],
            ];
            $rules['nohits_queries_'.$collection] = [
                'type'       => 'nohits_queries',
                'collection' => $collection,
                'event_type' => 'search',
                'rule_tag'   => 'no_hit_searches',
                'params'     => [
                    'destination_collection'  => self::NO_HIT_QUERIES_COLLECTION,
                    'limit'                   => 1000,
                    'capture_search_requests' => true,
                ],
            ];
        }

        if (in_array('products', $sourceCollections, true)) {
            $rules[self::PRODUCT_CLICK_EVENT] = [
                'type'       => 'counter',
                'collection' => 'products',
                'event_type' => 'click',
                'rule_tag'   => 'products_popularity',
                'params'     => [
                    'destination_collection' => 'products',
                    'counter_field'          => 'popularity',
                    'weight'                 => 1,
                ],
            ];
        }

        foreach ($rules as $name => $rule) {
            $response = $this->client()->put($base.'/analytics/rules/'.$name, $rule);
            $results["rule $name"] = $response->successful() ? 'ok' : $response->status().' '.$response->body();
        }

        return $results;
    }

    public function asCommand(Command $command): int
    {
        foreach ($this->handle() as $item => $status) {
            $command->line("$item: $status");
        }

        return 0;
    }

    protected function baseUrl(): string
    {
        $node = config('scout.typesense.client-settings.nodes.0');

        return $node['protocol'].'://'.$node['host'].':'.$node['port'];
    }

    protected function client(): PendingRequest
    {
        return Http::withHeaders([
            'X-TYPESENSE-API-KEY' => config('scout.typesense.client-settings.api_key'),
        ])->timeout(5);
    }
}
