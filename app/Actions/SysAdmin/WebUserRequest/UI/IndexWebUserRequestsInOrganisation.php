<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Jun 2025 23:49:07 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\WebUserRequest\UI;

use App\Actions\OrgAction;
use App\Actions\Overview\ShowOrganisationOverviewHub;
use App\Actions\SysAdmin\WebUserRequest\UI\Traits\WithWebUserRequestsUI;
use App\Actions\Traits\Authorisations\Inventory\WithOrganisationOverviewAuthorisation;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\ActionRequest;

class IndexWebUserRequestsInOrganisation extends OrgAction
{
    use WithOrganisationOverviewAuthorisation;
    use WithWebUserRequestsUI;


    public function handle(Organisation $organisation, $prefix = null): LengthAwarePaginator
    {
        $queryBuilder = $this->getWebUserRequestsQueryBuilder($prefix);
        $queryBuilder->where('web_user_requests.organisation_id', $organisation->id);

        return $this->finalizeWebUserRequestsQuery($queryBuilder, $prefix);
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowOrganisationOverviewHub::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.overview.web_user_requests.index',
                            'parameters' => $routeParameters
                        ],
                        'label' => __('Web User Requests'),
                    ]
                ]
            ]
        );
    }


}
