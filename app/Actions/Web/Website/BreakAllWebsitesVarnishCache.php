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
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class BreakAllWebsitesVarnishCache extends OrgAction
{
    use WithVarnishBan;

    public function handle(Command $command = null): void
    {
        $varnishCommand = "sudo varnishadm 'ban req.url ~ .'";

        $this->runVarnishCommand($varnishCommand, $command);
    }

    public function asController(ActionRequest $request): void
    {
        $this->initialisationFromGroup($this->group, $request);

        $this->handle();
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }

    public function getCommandSignature(): string
    {
        return 'websites:break_varnish_cache';
    }

    public function asCommand(Command $command): int
    {
        $this->handle();
        $command->info("All websites cache cleared");

        return 0;
    }

}
