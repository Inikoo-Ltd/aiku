<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 03 Jun 2024 23:34:44 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Authorisations;

use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

trait HasWebAuthorisation
{
    private Group|Organisation|Fulfilment|Shop $scope;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if ($this->scope instanceof Organisation) {
            $this->canEdit      = $request->user()->authTo("org-supervisor.{$this->organisation->id}");
            $this->isSupervisor = $request->user()->authTo("org-supervisor.{$this->organisation->id}");

            return $request->user()->authTo([
                "websites-view.{$this->organisation->id}",
                "group-webmaster.view"
            ]);
        } elseif ($this->scope instanceof Group) {
            return $request->user()->authTo("group-overview");
        } elseif ($this->scope instanceof Shop) {
            $this->canEdit      = $request->user()->authTo([
                "
            web.{$this->shop->id}.edit",
                "group-webmaster.view"
            ]);
            $this->isSupervisor = $request->user()->authTo([
                "supervisor-web.{$this->shop->id}",
                "group-webmaster.view"
            ]);

            return $request->user()->authTo(["web.{$this->shop->id}.view", "group-webmaster.view"]);
        } elseif ($this->scope instanceof Fulfilment) {
            $this->canEdit      = $request->user()->authTo([
                "fulfilment-shop.{$this->fulfilment->id}.edit",
                "group-webmaster.view"
            ]);
            $this->isSupervisor = $request->user()->authTo([
                "supervisor-fulfilment-shop.{$this->fulfilment->id}",
                "group-webmaster.view"
            ]);

            return $request->user()->authTo([
                "fulfilment-shop.{$this->fulfilment->id}.view",
                "group-webmaster.view"
            ]);
        }

        return false;
    }
}
