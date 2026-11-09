<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgPartner\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\OrgPartner\GetPartnerStockCoverBuckets;
use App\Models\Procurement\OrgPartner;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

/**
 * Why SKOs are left out of a rescue order, per bucket, loaded when Prepare order shows its reasons:
 * the cards skip it so they load fast. The buyer's procurement team reads it from its partner pages,
 * the hub's production team from the Intervention page.
 */
class GetPartnerRescueLeftOut extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        if (isset($this->production)) {
            return $this->organisation->is_manufacturing_hub && $request->user()->authTo([
                'org-supervisor.'.$this->organisation->id,
                'productions-view.'.$this->organisation->id,
                "productions_procurement.{$this->production->id}.view",
                "productions_procurement.{$this->production->id}.edit",
                "productions_operations.{$this->production->id}.orchestrate",
            ]);
        }

        return $request->user()->authTo("procurement.{$this->organisation->id}.view");
    }

    /**
     * @return array<string, object>
     */
    public function handle(OrgPartner $orgPartner): array
    {
        return GetPartnerStockCoverBuckets::make()->rescueLeftOut($orgPartner);
    }

    /**
     * @return array<string, object>
     */
    public function asController(OrgPartner $orgPartner, ActionRequest $request): array
    {
        $this->initialisation($orgPartner->organisation, $request);

        return $this->handle($orgPartner);
    }

    /**
     * @return array<string, object>
     */
    public function inIntervention(Organisation $organisation, Production $production, OrgPartner $orgPartner, ActionRequest $request): array
    {
        abort_unless($orgPartner->partner_id === $production->organisation_id && $orgPartner->organisation_id !== $production->organisation_id, 404);
        $this->initialisationFromProduction($production, $request);

        return $this->handle($orgPartner);
    }
}
