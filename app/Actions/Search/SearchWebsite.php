<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Jul 2026 08:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class SearchWebsite
{
    use AsAction;
    use WithRawSearchResults;

    public function handle(string $query, array $options): array
    {
        $webpagesQuery = Webpage::search($query);
        if ($shopId = Arr::get($options, 'shop_id')) {
            $webpagesQuery->where('shop_id', $shopId);
        }

        return [
            'scope'   => 'website',
            'results' => [
                'webpages' => array_map(static fn (array $document) => [
                    'id'    => (int)$document['id'],
                    'code'  => ($document['title'] ?? null) ?: ($document['code'] ?? null),
                    'name'  => $document['url'] ?? null,
                    'state' => $document['state'] ?? null,
                ], $this->rawDocuments($webpagesQuery)),
            ],
        ];
    }


}
