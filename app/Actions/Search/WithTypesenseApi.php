<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

trait WithTypesenseApi
{
    protected function typesenseUrl(): string
    {
        $node = config('scout.typesense.client-settings.nodes.0');

        return $node['protocol'].'://'.$node['host'].':'.$node['port'];
    }

    protected function typesenseClient(): PendingRequest
    {
        return Http::withHeaders([
            'X-TYPESENSE-API-KEY' => config('scout.typesense.client-settings.api_key'),
        ])->timeout(5);
    }
}
