<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Oct 2023 18:15:14 Malaysia Time, Office, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

declare(strict_types=1);

namespace App\Providers;

use App\Services\ScoutElasticSearch\EloquentHitsIteratorAggregate;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Support\ServiceProvider;
use Matchish\ScoutElasticSearch\ElasticSearch\HitsIteratorAggregate;

final class ElasticSearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {

        $this->app->bind(Client::class, function () {
            $clientBuilder = ClientBuilder::create();
            $clientBuilder->setHosts(explode(',', config('elasticsearch.host')));
            if (config('elasticsearch.ca_bundle')) {
                $clientBuilder->setCABundle(config('elasticsearch.ca_bundle'));
            }
            if (config('elasticsearch.api_key')) {
                $clientBuilder->setApiKey(config('elasticsearch.api_key'));
            }

            return $clientBuilder->build();
        });

        $this->app->bind(
            HitsIteratorAggregate::class,
            EloquentHitsIteratorAggregate::class
        );
    }

    public function provides(): array
    {
        return [Client::class];
    }
}
