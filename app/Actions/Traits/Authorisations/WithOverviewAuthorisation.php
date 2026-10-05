<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Sept 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Authorisations;

use Lorisleiva\Actions\ActionRequest;

/**
 * The overviews list the customers, invoices and mailshots of every shop: the group one is for group-overview
 * holders, an organisation's one for its report readers. The shop pages sharing these actions are unchanged.
 */
trait WithOverviewAuthorisation
{
    public function authorize(ActionRequest $request): bool
    {
        $routeName = (string) $request->route()?->getName();

        if (str_starts_with($routeName, 'grp.overview.')) {
            return $request->user()->authTo('group-overview');
        }

        if (str_starts_with($routeName, 'grp.org.overview.')) {
            return $request->user()->authTo('org-reports.'.$this->organisation->id);
        }

        return true;
    }
}
