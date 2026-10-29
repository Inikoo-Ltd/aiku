<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-10-29 15:41
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Traits\Authorisations;

use Lorisleiva\Actions\ActionRequest;

/**
 * Procurement staff of an agent organisation see all of it. An agent products viewer sees only the Products and
 * Warehouse menus, read only, without costs and without links into orders, suppliers, accounting or reports.
 */
trait WithAgentAreaAuthorisation
{
    protected bool $hideProcurementDetails = false;

    protected function authorizeAgentArea(ActionRequest $request, string $area): bool
    {
        if ($this->asAction) {
            return true;
        }

        $user = $request->user();

        $this->canEdit   = $user->authTo("procurement.{$this->organisation->id}.edit");
        $this->canDelete = $this->canEdit;

        if ($user->authTo("procurement.{$this->organisation->id}.view")) {
            return true;
        }

        $this->hideProcurementDetails = true;

        return $user->authTo("agent.{$this->organisation->id}.$area.view");
    }
}
