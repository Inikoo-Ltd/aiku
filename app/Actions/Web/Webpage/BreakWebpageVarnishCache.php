<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Oct 2025 19:32:46 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Actions\OrgAction;
use App\Models\Web\Webpage;
use Illuminate\Console\Command;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;
use App\Actions\Traits\WithVarnishBan;

class BreakWebpageVarnishCache extends OrgAction
{
    use WithVarnishBan;

    public function handle(Webpage $webpage, Command $command = null): Webpage
    {


        $banExpr        = "obj.http.x-aiku-webpage == $webpage->id";
        $varnishCommand = "sudo varnishadm 'ban $banExpr'";


        $this->runVarnishCommand($varnishCommand, $command);

        return $webpage;
    }

    public function asController(Webpage $webpage, ActionRequest $request): Webpage
    {
        $this->initialisationFromShop($webpage->shop, $request);

        return $this->handle($webpage);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }

    public function getCommandSignature(): string
    {
        return 'webpage:break_varnish_cache {slug}';
    }

    public function asCommand(Command $command): int
    {
        $webpage = Webpage::where('slug', $command->argument('slug'))->first();
        $this->handle($webpage, $command);

        return 0;
    }

}
