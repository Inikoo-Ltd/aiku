<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Oct 2025 19:32:46 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website;

use App\Actions\OrgAction;
use App\Models\Web\Website;
use Illuminate\Console\Command;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;
use App\Actions\Traits\WithVarnishBan;

class BreakWebsiteVarnishCache extends OrgAction
{
    use WithVarnishBan;

    public function handle(Website $website, Command $command = null): Website
    {


        $banExpr        = "obj.http.X-AIKU-WEBSITE == $website->id";
        $varnishCommand = "sudo varnishadm 'ban $banExpr'";

        $this->runVarnishCommand($varnishCommand, $command);

        return $website;
    }

    public function asController(Website $website, ActionRequest $request): Website
    {
        $this->initialisationFromShop($website->shop, $request);

        return $this->handle($website);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }

    public function getCommandSignature(): string
    {
        return 'website:break_varnish_cache {slug}';
    }

    public function asCommand(Command $command): int
    {
        $website = Website::where('slug', $command->argument('slug'))->first();
        $this->handle($website, $command);

        return 0;
    }

}
