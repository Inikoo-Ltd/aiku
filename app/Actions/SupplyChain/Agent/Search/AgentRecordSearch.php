<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 18 Jan 2024 17:14:23 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Agent\Search;

use App\Models\SupplyChain\Agent;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class AgentRecordSearch implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'universal-search';

    public function getJobUniqueId(Agent $agent): string
    {
        return $agent->id;
    }

    public function handle(Agent $agent): void
    {
        if ($agent->trashed()) {
            $agent->universalSearch()->delete();

            return;
        }

        $agent->universalSearch()->updateOrCreate(
            [],
            [
                'group_id' => $agent->group_id,
                'sections' => ['supply-chain'],
                'haystack_tier_1' => trim($agent->organisation->name.' '.$agent->organisation->email.' '.$agent->organisation->phone),
            ]
        );
    }

}
