<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 03 Apr 2024 20:54:46 Central Indonesia Time, Bali Office , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgAgent;

use App\Actions\OrgAction;
use App\Actions\Procurement\OrgAgent\Search\OrgAgentRecordSearch;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrgAgents;
use App\Models\Procurement\OrgAgent;
use App\Models\SupplyChain\Agent;
use App\Models\SysAdmin\Organisation;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\ActionRequest;

class StoreOrgAgent extends OrgAction
{
    public function handle(Organisation $organisation, Agent $agent, $modelData = []): OrgAgent
    {
        data_set($modelData, 'group_id', $organisation->group_id);
        data_set($modelData, 'organisation_id', $organisation->id);
        data_set($modelData, 'status', $agent->status, false);

        /** @var OrgAgent $orgAgent */
        $orgAgent = $agent->orgAgents()->create($modelData);
        $orgAgent->stats()->create();
        OrganisationHydrateOrgAgents::dispatch($orgAgent->organisation);
        OrgAgentRecordSearch::dispatch($orgAgent);

        return $orgAgent;
    }

    public function rules(ActionRequest $request): array
    {
        return [
            'source_id' => 'sometimes|nullable|string',
        ];
    }

    public function action(Organisation $organisation, Agent $agent, $modelData, $hydratorsDelay = 0): OrgAgent
    {
        $this->asAction = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($organisation, $modelData);

        return $this->handle($organisation, $agent, $this->validatedData);
    }

    public function getCommandSignature(): string
    {
        return 'org:attach_agent {organisation} {agent}';
    }

    public function asCommand(Command $command): int
    {
        try {
            $organisation = Organisation::where('slug', $command->argument('organisation'))->firstOrFail();
        } catch (Exception $e) {
            $command->error($e->getMessage());

            return 1;
        }

        try {
            $agent = Agent::where('slug', $command->argument('agent'))->firstOrFail();
        } catch (Exception $e) {
            $command->error($e->getMessage());

            return 1;
        }

        $this->handle($organisation, $agent);
        $command->info('Agent attached to organisation');

        return 0;
    }
}
