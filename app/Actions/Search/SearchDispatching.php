<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 Jul 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Models\Dispatching\DeliveryNote;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class SearchDispatching
{
    use AsAction;
    use WithRawSearchResults;

    public function handle(string $query, array $options): array
    {
        $deliveryNotesQuery = DeliveryNote::search($query);
        if ($organisationId = Arr::get($options, 'organisation_id')) {
            $deliveryNotesQuery->where('organisation_id', $organisationId);
        }

        return [
            'scope'   => 'dispatching',
            'results' => [
                'delivery_notes' => array_map(static fn (array $document) => [
                    'id'    => (int)$document['id'],
                    'code'  => $document['reference'] ?? null,
                    'name'  => ($document['contact_name'] ?? null) ?: ($document['company_name'] ?? null),
                    'state' => $document['state'] ?? null,
                ], $this->rawDocuments($deliveryNotesQuery)),
            ],
        ];
    }


}
