<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Authorisations;

use Lorisleiva\Actions\ActionRequest;

trait WithShopDashboardAuthorisation
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $user = $request->user();

        return $user->authTo([
            "org-admin.{$this->organisation->id}",
            "org-supervisor.{$this->organisation->id}",
            "accounting.{$this->organisation->id}.view",
        ]) || $user->authorisedShops()->where('shops.id', $this->shop->id)->exists();
    }
}
