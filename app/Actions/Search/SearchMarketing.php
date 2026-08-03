<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Jul 2026 08:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Models\Comms\Mailshot;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class SearchMarketing
{
    use AsAction;
    use WithRawSearchResults;

    public function handle(string $query, array $options): array
    {
        $mailshotsQuery = Mailshot::search($query);
        if ($shopId = Arr::get($options, 'shop_id')) {
            $mailshotsQuery->where('shop_id', $shopId);
        }

        return [
            'scope'   => 'marketing',
            'results' => [
                'mailshots' => array_map(static fn (array $document) => [
                    'id'    => (int)$document['id'],
                    'code'  => $document['subject'] ?? null,
                    'name'  => $document['type'] ?? null,
                    'state' => $document['state'] ?? null,
                ], $this->rawDocuments($mailshotsQuery)),
            ],
        ];
    }


}
