<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 04 May 2025 12:42:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Authorisations\Ordering;

use Lorisleiva\Actions\ActionRequest;

trait WithOrderingAuthorisation
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $routeName = $request->route()->getName();

        // Group level authorization - only for grp.overview.ordering and grp.dashboard.show (not grp.org.dashboard)
        if (str_starts_with($routeName, 'grp.overview.ordering') || $routeName === 'grp.dashboard.show') {
            return $request->user()->authTo("group-overview");
        }

        // Organisation level authorization - only for grp.org.overview.ordering and grp.org.dashboard.show
        if (str_starts_with($routeName, 'grp.org.overview.ordering') || $routeName === 'grp.org.dashboard.show') {
            if (isset($this->organisation)) {
                $this->canEdit = $request->user()->authTo("orders.{$this->organisation->id}.edit");
                return $request->user()->authTo(["orders.{$this->organisation->id}.view", "accounting.{$this->organisation->id}.view"]);
            }
            return false;
        }

        // Shop level authorization with CRM
        if (str_starts_with($routeName, 'grp.org.shops.show.crm..')) {
            $this->canEdit = $request->user()->authTo(["orders.{$this->shop->id}.edit", "crm.{$this->shop->id}.edit"]);

            return $request->user()->authTo(["crm.{$this->shop->id}.view", "accounting.{$this->shop->organisation_id}.view"]);
        }

        // Default shop level authorization
        if (isset($this->shop)) {
            $this->canEdit = $request->user()->authTo("orders.{$this->shop->id}.edit");
            return $request->user()->authTo(["orders.{$this->shop->id}.view", "accounting.{$this->shop->organisation_id}.view"]);
        }

        // If no specific authorization matched, deny access
        return false;
    }
}
