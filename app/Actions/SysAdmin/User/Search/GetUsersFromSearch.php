<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Jul 2026 10:42:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User\Search;

use App\Http\Resources\SysAdmin\User\GerUsersFromSearchResource;
use App\Http\Resources\SysAdmin\User\UserSearchResultResource;
use App\Models\SysAdmin\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\Concerns\AsAction;

class GetUsersFromSearch
{
    use asAction;

    public function handle(string $search): AnonymousResourceCollection
    {
        $users = User::search($search)->get();

        return GerUsersFromSearchResource::collection($users);
    }

}