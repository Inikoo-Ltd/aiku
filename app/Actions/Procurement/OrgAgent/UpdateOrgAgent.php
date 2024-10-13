<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 03 Apr 2024 20:54:46 Central Indonesia Time, Bali Office , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgAgent;

use App\Actions\OrgAction;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrgAgents;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Procurement\OrgAgent;
use Lorisleiva\Actions\ActionRequest;

class UpdateOrgAgent extends OrgAction
{
    use WithActionUpdate;


    public function handle(OrgAgent $orgAgent, $modelData = []): OrgAgent
    {

        $orgAgent = $this->update($orgAgent, $modelData);

        OrganisationHydrateOrgAgents::dispatch($orgAgent->organisation);

        return $orgAgent;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction = true) {
            return true;
        }

        return $request->user()->hasPermissionTo("procurement.".$this->organisation->id.".edit");
    }

    public function rules(ActionRequest $request): array
    {
        return [
            'source_id'   => 'sometimes|nullable|string|max:64',
            'status'      => ['sometimes', 'required', 'boolean'],
        ];
    }

    public function action(OrgAgent $orgAgent, $modelData, $hydratorsDelay = 0): OrgAgent
    {
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($orgAgent->organisation, $modelData);

        return $this->handle($orgAgent, $this->validatedData);
    }


}
