<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 Apr 2026 02:49:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Actions\OrgAction;
use Lorisleiva\Actions\ActionRequest;

class Search extends OrgAction
{
    public function handle(string $scope, string $query): array
    {
        if ($scope === 'sysadmin') {
            return SearchSysAdmin::run($query);
        }
        return [];
    }

    public function rules(): array
    {
        return [
            'q' => ['required', 'string'],
        ];
    }

    public function asController(ActionRequest $request): array
    {
        $route = $request->input('route_src');
        $scope = $this->getRouteScope($route);

        if ($scope === 'sysadmin') {
            $this->initialisationFromGroup(app('group'), $request);
            return $this->handle($scope, $this->validatedData['q']);
        }
        return [];
    }

    public function getRouteScope($route): ?string
    {
        if (str_starts_with($route, 'grp.sysadmin')) {
            return 'sysadmin';
        }
        return null;
    }

}
