<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Oct 2025 19:32:46 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website;

use App\Actions\OrgAction;
use App\Actions\Traits\WithVarnishBan;
use Illuminate\Console\Command;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Support\Facades\Process;

class BreakAllWebsitesVarnishCache extends OrgAction
{
    use WithVarnishBan;

    public function handle(?Command $command = null): array
    {
        $result = Process::timeout(1800)->run('./restart_varnish.sh');

        if ($command) {
            if ($result->successful()) {
                $command->info("All websites cache cleared");
            } else {
                $command->error("Failed to restart varnish");
            }
        }

        return [];
    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle();
    }


    public function getCommandSignature(): string
    {
        return 'varnish:restart {delay?}';
    }

    public function asCommand(Command $command): int
    {
        if ($delay = $command->argument('delay')) {
            sleep((int) $delay);
        }

        $this->handle($command);

        return 0;
    }

}
