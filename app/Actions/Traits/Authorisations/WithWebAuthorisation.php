<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 03 Jun 2024 23:34:44 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Authorisations;

use Lorisleiva\Actions\ActionRequest;

trait WithWebAuthorisation
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $routeName = $request->route()->getName();
        $user = $request->user();

        // Helper for org supervisor routes
        $orgSupervisorRoutes = [
            'grp.org.websites.',
            'grp.models.org.product.',
            'grp.models.webpage.'
        ];

        foreach ($orgSupervisorRoutes as $prefix) {
            if (str_starts_with($routeName, $prefix)) {
                $this->canEdit = $user->authTo("org-supervisor.{$this->organisation->id}");
                $this->isSupervisor = $this->canEdit;
                return $user->authTo([
                    "websites-view.{$this->organisation->id}",
                    "group-webmaster.view"
                ]);
            }
        }

        if (str_starts_with($routeName, 'grp.overview.')) {
            return $user->authTo("group-overview");
        }

        if (str_starts_with($routeName, 'grp.org.shops.show.web.')) {
            $this->canEdit = $user->authTo([
                "web.{$this->shop->id}.edit",
                "group-webmaster.edit"
            ]);
            $this->isSupervisor = $user->authTo([
                "supervisor-web.{$this->shop->id}",
                "group-webmaster.edit"
            ]);
            return $user->authTo([
                "web.{$this->shop->id}.view",
                "group-webmaster.view"
            ]);
        }

        if (str_starts_with($routeName, 'grp.org.fulfilments.show.web.')) {
            $this->canEdit = $user->authTo([
                "fulfilment-shop.{$this->fulfilment->id}.edit",
                "group-webmaster.edit"
            ]);
            $this->isSupervisor = $user->authTo([
                "supervisor-fulfilment-shop.{$this->fulfilment->id}",
                "group-webmaster.edit"
            ]);
            return $user->authTo([
                "fulfilment-shop.{$this->fulfilment->id}.view",
                "group-webmaster.view"
            ]);
        }

        return false;
    }
}
