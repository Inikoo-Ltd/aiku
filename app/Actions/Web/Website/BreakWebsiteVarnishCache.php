<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Oct 2025 19:32:46 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website;

use App\Actions\OrgAction;
use App\Actions\Traits\WithVarnishBan;
use App\Models\Web\Website;
use Illuminate\Console\Command;
use Lorisleiva\Actions\ActionRequest;

class BreakWebsiteVarnishCache extends OrgAction
{
    use WithVarnishBan;

    public function handle(Website $website, ?Command $command = null): array
    {

        return $this->sendVarnishBanHttp([
            'x-ban-website' => $website->id,
        ], $command);

    }

    public function asController(Website $website, ActionRequest $request): array
    {
        $this->initialisationFromShop($website->shop, $request);

        return $this->handle($website);
    }

    public function getCommandSignature(): string
    {
        return 'varnish:website {slug}';
    }

    public function asCommand(Command $command): int
    {
        $website = Website::where('slug', $command->argument('slug'))->first();
        $this->handle($website, $command);

        return 0;
    }
}
