<?php

namespace App\Actions\HumanResources\Leave;

use App\Actions\OrgAction;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetUsersForDropdown extends OrgAction
{
    public function handle(Organisation $organisation): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->where('users.contact_name', 'like', '%' . $value . '%')
                    ->orWhere('users.email', 'like', '%' . $value . '%');
            });
        });

        return QueryBuilder::for(User::class)
            ->leftJoin('user_has_authorised_models', function ($join) use ($organisation) {
                $join->on('user_has_authorised_models.user_id', '=', 'users.id')
                    ->where('user_has_authorised_models.org_id', $organisation->id);
            })
            ->whereNotNull('user_has_authorised_models.id')
            ->select('users.id as value', 'users.contact_name as label')
            ->allowedFilters([$globalSearch])
            ->withPaginator(null)
            ->withQueryString();
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }
}
