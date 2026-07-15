<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 Apr 2026 03:07:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Models\SysAdmin\Guest;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\Concerns\AsAction;

class SearchSysAdmin
{
    use AsAction;
    use WithRawSearchResults;

    public function handle(string $query): array
    {
        return [
            'scope'   => 'sysadmin',
            'results' => [
                'users'  => array_map(static fn (array $document) => [
                    'username'          => $document['username'] ?? null,
                    'email'             => $document['email'] ?? null,
                    'contact_name'      => $document['contact_name'] ?? null,
                    'status'            => $document['status'] ?? null,
                    'organisation_code' => 'X',
                ], $this->rawDocuments(User::search($query))),
                'guests' => array_map(static fn (array $document) => [
                    'id'           => (int)$document['id'],
                    'slug'         => $document['slug'] ?? null,
                    'code'         => $document['code'] ?? null,
                    'contact_name' => $document['contact_name'] ?? null,
                    'email'        => $document['email'] ?? null,
                ], $this->rawDocuments(Guest::search($query))),
            ],
        ];
    }


}
