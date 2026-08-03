<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Jul 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Models\HumanResources\Employee;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class SearchHr
{
    use AsAction;
    use WithRawSearchResults;

    public function handle(string $query, array $options): array
    {
        $employeesQuery = Employee::search($query);
        if ($organisationId = Arr::get($options, 'organisation_id')) {
            $employeesQuery->where('organisation_id', $organisationId);
        }

        return [
            'scope'   => 'hr',
            'results' => [
                'employees' => array_map(static fn (array $document) => [
                    'id'           => (int)$document['id'],
                    'code'         => $document['alias'] ?? null,
                    'name'         => $document['contact_name'] ?? null,
                    'company_name' => $document['job_title'] ?? null,
                    'email'        => $document['email'] ?? null,
                    'phone'        => $document['phone'] ?? null,
                    'state'        => $document['state'] ?? null,
                ], $this->rawDocuments($employeesQuery)),
            ],
        ];
    }


}
