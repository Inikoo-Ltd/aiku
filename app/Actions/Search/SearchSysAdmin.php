<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 Apr 2026 03:07:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Http\Resources\SysAdmin\Guest\GuestSearchResultResource;
use App\Http\Resources\SysAdmin\User\UserSearchResultResource;
use App\Models\SysAdmin\Guest;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\Concerns\AsAction;

class SearchSysAdmin
{
    use AsAction;

    public function handle(string $query): array
    {
        $users  = User::search($query)->get();
        $guests = Guest::search($query)->get();

        return [
            'results' => [
                'users'  => UserSearchResultResource::collection($users),
                'guests' => GuestSearchResultResource::collection($guests),
            ]
        ];
    }


}
