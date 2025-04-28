<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 18:06:06 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Authorisations;

use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

trait HasOrderingAuthorisation
{
    private Organisation|Shop $scope;
    private string $authorisationType = 'view';

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return match ($this->authorisationType) {
            'view' => $this->viewAuthorisation($request),
            'edit', 'update' => $this->editAuthorisation($request),
            default => false,
        };
    }

    private function viewAuthorisation(ActionRequest $request)
    {
        $routeName = $request->route()->getName();

        if (str_starts_with($routeName, 'grp.org.shops.show')) {
            $this->canEdit      = $request->user()->authTo(
                [
                    "orders.{$this->shop->id}.edit",
                    "org-supervisor.{$this->organisation->id}"
                ]
            );
            $this->isSupervisor = $request->user()->authTo(
                [
                    "supervisor-orders.{$this->shop->id}",
                    "org-supervisor.{$this->organisation->id}"
                ]
            );

            return $request->user()->authTo([
                "orders.{$this->shop->id}.view",
                "org-supervisor.{$this->organisation->id}"
            ]);
        }

        $this->canEdit      = $request->user()->authTo("org-supervisor.{$this->organisation->id}");
        $this->isSupervisor = $request->user()->authTo("org-supervisor.{$this->organisation->id}");

        return $request->user()->authTo("websites - view.{$this->organisation->id}");
    }

    private function editAuthorisation(ActionRequest $request)
    {
        if ($this->scope instanceof Organisation) {
            return $request->user()->authTo("org-supervisor.{$this->organisation->id}");
        } else {
            return $request->user()->authTo("orders.{$this->shop->id}.edit");
        }
    }

}
