<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Jul 2026 10:42:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User\Search;

use App\Http\Resources\SysAdmin\User\GerUsersFromSearchResource;
use App\Models\SysAdmin\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetUsersFromSearch
{
    use asAction;

    public function handle(string $search, bool $onlyActive = true): AnonymousResourceCollection
    {
        $users = User::search($search)->get();
        if ($onlyActive) {
            $users = $users->where('status', true);
        }

        $users->load('employedInOrganisation');

        return GerUsersFromSearchResource::collection($users);
    }

    public function asController(ActionRequest $request): AnonymousResourceCollection
    {
        return $this->handle((string) ($request->input('filter.global') ?? $request->query('search', '')));
    }
}
