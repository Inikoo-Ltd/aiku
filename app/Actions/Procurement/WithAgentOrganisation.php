<?php

namespace App\Actions\Procurement;

use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\SupplyChain\Agent;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Model;

trait WithAgentOrganisation
{
    protected function getOrganisationAgent(Organisation $organisation): ?Agent
    {
        if ($organisation->type !== OrganisationTypeEnum::AGENT) {
            return null;
        }

        return $organisation->agent;
    }

    protected function getParentOrganisationAgent(Model $parent): ?Agent
    {
        return $parent instanceof Organisation ? $this->getOrganisationAgent($parent) : null;
    }

    protected function authorizeProcurementRecord(Model $record): void
    {
        if ($record->organisation_id === $this->organisation->id) {
            return;
        }

        $agent = $this->getOrganisationAgent($this->organisation);

        if (!$agent || $this->getProcurementRecordAgentId($record) !== $agent->id) {
            abort(404);
        }

        $this->canEdit   = false;
        $this->canDelete = false;
    }

    private function getProcurementRecordAgentId(Model $record): ?int
    {
        if ($record instanceof OrgSupplierProduct) {
            return $record->orgAgent?->agent_id;
        }

        return $record->agent_id;
    }
}
