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

        $result=Process::run('./restart_varnish.sh');

        if($command) {
            if ($result->successful()) {
                $command->info("All websites cache cleared");
            } else {
                $command->error("Failed to restart varnish");
            }
        }
        return [];

        //        return $this->sendVarnishBanHttp(
//            [
//                'x-ban-all' => 'all'
//            ],
//            $command
//        );
    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle();
    }


    public function getCommandSignature(): string
    {
        return 'vanish:restart';
    }

    public function asCommand(Command $command): int
    {
        $this->handle($command);

        return 0;
    }

}
