<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 25 Feb 2024 10:01:43 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Authorisations;

use App\Models\SysAdmin\Organisation;

use Lorisleiva\Actions\ActionRequest;

trait WithCRMAuthorisation
{
    public function authorize(ActionRequest $request): bool
    {
        $routeName = $request->route()->getName();

        $organisationParameter = $request->route('organisation');

        if ($organisationParameter) {
            $this->organisation = Organisation::where('slug', $organisationParameter)->first();
            if (!$this->organisation) {
                return false;
            }
        }

        if (str_starts_with($routeName, 'grp.overview.')) {
            return $request->user()->authTo("group-overview");
        } elseif (str_starts_with($routeName, 'grp.org.shops.show.crm.prospects')) {
            $this->canEdit = $request->user()->authTo("crm.{$this->shop->id}.prospects.edit");

            if (str_ends_with($routeName, '.edit') || str_ends_with($routeName, '.create')) {
                return $this->canEdit;
            }

            return $request->user()->authTo(
                [
                    "crm.{$this->shop->id}.prospects.view"
                ]
            );
        } elseif (str_starts_with($routeName, 'grp.org.shops.show.crm.')) {
            $this->canEdit = $request->user()->authTo("crm.{$this->shop->id}.edit");
            if (str_ends_with($routeName, '.edit') || str_ends_with($routeName, '.create')) {
                return $this->canEdit;
            }

            return $request->user()->authTo(
                [
                    "crm.{$this->shop->id}.view",
                    "accounting.{$this->shop->organisation_id}.view"
                ]
            );
        } elseif (str_starts_with($routeName, 'grp.org.fulfilments.show.crm.')) {
            $this->canEdit = $request->user()->authTo("fulfilment-shop.{$this->shop->fulfilment->id}.edit");
            if (str_ends_with($routeName, '.edit') || str_ends_with($routeName, '.create')) {
                return $this->canEdit;
            }

            return $request->user()->authTo(
                [
                    "fulfilment-shop.{$this->shop->fulfilment->id}.view",
                    "accounting.{$this->shop->organisation_id}.view"
                ]
            );
        } elseif (str_starts_with($routeName, 'grp.org.suppliers.')) {
            if ($this->organisation) {
                $requiredPermission = "supplier.{$this->organisation->id}.view";

                if (str_ends_with($routeName, '.edit') || str_ends_with($routeName, '.create')) {
                    $requiredPermission = "supplier.{$this->organisation->id}.edit";
                }

                return $request->user()->authTo($requiredPermission);
            }
        }

        return false;
    }
}
