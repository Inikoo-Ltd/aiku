<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 Jul 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Overview;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Number;
use Lorisleiva\Actions\Concerns\AsObject;
use Throwable;

class GetTypesenseMetrics
{
    use AsObject;

    public function handle(): ?array
    {
        return cache()->remember('typesense-metrics', 60, function () {
            try {
                $config  = config('scout.typesense.client-settings');
                $node    = $config['nodes'][0];
                $baseUrl = $node['protocol'].'://'.$node['host'].':'.$node['port'];
                $headers = ['X-TYPESENSE-API-KEY' => $config['api_key']];

                $metrics     = Http::withHeaders($headers)->timeout(2)->get($baseUrl.'/metrics.json')->json();
                $collections = Http::withHeaders($headers)->timeout(2)->get($baseUrl.'/collections')->json();

                $collections = collect($collections)
                    ->map(function (array $collection) use ($baseUrl, $headers) {
                        $sizeBytes = 0;
                        if ($collection['num_documents'] > 0) {
                            $sample = Http::withHeaders($headers)->timeout(2)
                                ->get($baseUrl.'/collections/'.$collection['name'].'/documents/search', [
                                    'q'        => '*',
                                    'per_page' => 20,
                                ])->json('hits', []);
                            $sampleBytes = array_sum(array_map(static fn (array $hit) => strlen(json_encode($hit['document'])), $sample));
                            if (count($sample)) {
                                $sizeBytes = (int)($sampleBytes / count($sample) * $collection['num_documents']);
                            }
                        }

                        return [
                            'name'       => $collection['name'],
                            'documents'  => $collection['num_documents'],
                            'size_bytes' => $sizeBytes,
                            'size'       => Number::fileSize($sizeBytes, precision: 1),
                        ];
                    })
                    ->sortByDesc('documents')
                    ->values()
                    ->all();

                return [
                    'memory'          => Number::fileSize((int)($metrics['typesense_memory_active_bytes'] ?? 0), precision: 2),
                    'total_documents' => array_sum(array_column($collections, 'documents')),
                    'collections'     => $collections,
                ];
            } catch (Throwable) {
                return null;
            }
        });
    }
}
