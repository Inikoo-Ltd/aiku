<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Jun 2025 23:49:07 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\WebUserRequest\UI;

use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\SysAdmin\WebUserRequest\UI\Traits\WithWebUserRequestsUI;
use App\Actions\Traits\Authorisations\Inventory\WithGroupOverviewAuthorisation;
use App\Models\SysAdmin\Group;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\ActionRequest;

class IndexWebUserRequestsInGroup extends OrgAction
{
    use WithGroupOverviewAuthorisation;
    use WithWebUserRequestsUI;


    public function handle(Group $group, $prefix = null): LengthAwarePaginator
    {
        $queryBuilder = $this->getWebUserRequestsQueryBuilder($prefix);
        $queryBuilder->where('web_user_requests.group_id', $group->id);

        return $this->finalizeWebUserRequestsQuery($queryBuilder, $prefix);
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($this->group);
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowGroupOverviewHub::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.overview.web.web_user_requests.index',
                            'parameters' => $routeParameters
                        ],
                        'label' => __('Web User Requests'),
                    ]
                ]
            ]
        );
    }


}
