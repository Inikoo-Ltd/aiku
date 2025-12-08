<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 13 Dec 2023 23:50:27 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Events;

use App\Enums\CRM\Prospect\ProspectContactedStateEnum;
use App\Enums\CRM\Prospect\ProspectFailStatusEnum;
use App\Enums\CRM\Prospect\ProspectSuccessStatusEnum;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;

class BroadcastProspectsDashboard implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public array $data;

    public function __construct(array $changes)
    {
        $stats = [];

        if (Arr::has($changes, 'number_prospects')) {
            data_set($stats, 'counts.prospects', $changes['number_prospects']);
        }
        if (Arr::has($changes, 'number_prospects_state_no_contacted')) {
            data_set($stats, 'counts.no-contacted', $changes['number_prospects_state_no_contacted']);
        }
        if (Arr::has($changes, 'number_prospects_state_contacted')) {
            data_set($stats, 'counts.contacted', $changes['number_prospects_state_contacted']);
        }
        if (Arr::has($changes, 'number_prospects_state_fail')) {
            data_set($stats, 'counts.fail', $changes['number_prospects_state_fail']);
        }
        if (Arr::has($changes, 'number_prospects_state_success')) {
            data_set($stats, 'counts.success', $changes['number_prospects_state_success']);
        }

        foreach (ProspectContactedStateEnum::cases() as $case) {
            if ($case == ProspectContactedStateEnum::NA) {
                continue;
            }
            if (Arr::has($changes, 'number_prospects_contacted_state_'.$case->snake())) {
                data_set($stats, 'contacted.'.$case->value, $changes['number_prospects_contacted_state_'.$case->snake()]);
            }
        }

        foreach (ProspectFailStatusEnum::cases() as $case) {
            if ($case == ProspectFailStatusEnum::NA) {
                continue;
            }
            if (Arr::has($changes, 'number_prospects_fail_status_'.$case->snake())) {
                data_set($stats, 'fail.'.$case->value, $changes['number_prospects_fail_status_'.$case->snake()]);
            }
        }

        foreach (ProspectSuccessStatusEnum::cases() as $case) {
            if ($case == ProspectSuccessStatusEnum::NA) {
                continue;
            }
            if (Arr::has($changes, 'number_prospects_success_status_'.$case->snake())) {
                data_set($stats, 'success.'.$case->value, $changes['number_prospects_success_status_'.$case->snake()]);
            }
        }

        $this->data = $stats;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('org.general'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'prospects.dashboard';
    }
}
