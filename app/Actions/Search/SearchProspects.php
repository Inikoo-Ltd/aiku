<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 May 2026 11:18:32 Nepal Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Models\CRM\Prospect;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class SearchProspects
{
    use AsAction;
    use WithRawSearchResults;

    public function handle(string $query, array $options): array
    {
        $prospectsQuery = Prospect::search($query);
        if ($shopId = Arr::get($options, 'shop_id')) {
            $prospectsQuery->where('shop_id', $shopId);
        }

        return [
            'scope'   => 'prospects',
            'results' => [
                'prospects' => array_map(static fn (array $document) => [
                    'id'           => (int)$document['id'],
                    'name'         => $document['name'] ?? null,
                    'contact_name' => $document['contact_name'] ?? null,
                    'company_name' => $document['company_name'] ?? null,
                    'email'        => $document['email'] ?? null,
                    'phone'        => $document['phone'] ?? null,
                    'state'        => $document['state'] ?? null,
                ], $this->rawDocuments($prospectsQuery)),
            ],
        ];
    }


}
