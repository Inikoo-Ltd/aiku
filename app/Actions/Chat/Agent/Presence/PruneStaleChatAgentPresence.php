<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 04 Aug 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Agent\Presence;

use App\Actions\Chat\Agent\UpdateAgent;
use App\Enums\CRM\Livechat\ChatAgentPresenceStatusEnum;
use App\Models\Chat\ChatAgent;
use Illuminate\Console\Command;
use Illuminate\Contracts\Database\Query\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

class PruneStaleChatAgentPresence
{
    use AsAction;

    public string $commandSignature = 'chat:prune-agent-presence';

    public string $commandDescription = 'Mark chat agents offline once their heartbeat has gone stale';

    public function handle(): int
    {
        $agents = ChatAgent::query()
            ->where(function (Builder $claimsPresence) {
                $claimsPresence->where('is_online', true)
                    ->orWhere('presence_status', '!=', ChatAgentPresenceStatusEnum::OFFLINE->value);
            })
            ->where(function (Builder $withoutFreshHeartbeat) {
                $withoutFreshHeartbeat->whereNull('last_heartbeat_at')
                    ->orWhere('last_heartbeat_at', '<=', ChatAgent::heartbeatCutOff());
            })
            ->get();

        foreach ($agents as $agent) {
            UpdateAgent::make()->setOffline($agent->user_id);
        }

        return $agents->count();
    }


    public function asCommand(Command $command): int
    {
        $command->info(sprintf('%d chat agent(s) marked offline', $this->handle()));

        return 0;
    }
}
