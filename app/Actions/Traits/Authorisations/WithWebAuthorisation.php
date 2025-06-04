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

        if (str_starts_with($routeName, 'grp.org.websites.')) {
            $this->canEdit      = $request->user()->authTo("org-supervisor.{$this->organisation->id}");
            $this->isSupervisor = $request->user()->authTo("org-supervisor.{$this->organisation->id}");

            return $request->user()->authTo([
                "websites-view.{$this->organisation->id}",
                "group-webmaster.view"
            ]);
        } elseif (str_starts_with($routeName, 'grp.overview.')) {
            return $request->user()->authTo("group-overview");
        } elseif (str_starts_with($routeName, 'grp.org.shops.show.web.')) {
            $this->canEdit      = $request->user()->authTo([
                "web.{$this->shop->id}.edit",
                "group-webmaster.edit"
            ]);
            $this->isSupervisor = $request->user()->authTo([
                "supervisor-web.{$this->shop->id}",
                "group-webmaster.edit"
            ]);

            return $request->user()->authTo(["web.{$this->shop->id}.view", "group-webmaster.view"]);
        } elseif (str_starts_with($routeName, 'grp.org.fulfilments.show.web.')) {
            $this->canEdit      = $request->user()->authTo([
                "fulfilment-shop.{$this->fulfilment->id}.edit",
                "group-webmaster.edit"
            ]);
            $this->isSupervisor = $request->user()->authTo([
                "supervisor-fulfilment-shop.{$this->fulfilment->id}",
                "group-webmaster.edit"
            ]);

            return $request->user()->authTo([
                "fulfilment-shop.{$this->fulfilment->id}.view",
                "group-webmaster.view"
            ]);
        } elseif (str_starts_with($routeName, 'grp.models.org.product.')) {
            $this->canEdit      = $request->user()->authTo("org-supervisor.{$this->organisation->id}");
            $this->isSupervisor = $request->user()->authTo("org-supervisor.{$this->organisation->id}");

            return $request->user()->authTo([
                "websites-view.{$this->organisation->id}",
                "group-webmaster.view"
            ]);
        }

        return false;
    }
}
