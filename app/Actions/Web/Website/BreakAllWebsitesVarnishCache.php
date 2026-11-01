<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Oct 2025 19:32:46 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website;

use App\Actions\OrgAction;
use App\Actions\Traits\WithVarnishBan;
use App\Actions\Web\Website\Cloudflare\PurgeCloudflareWebsitePages;
use App\Models\Web\Website;
use Illuminate\Console\Command;
use Lorisleiva\Actions\ActionRequest;

class BreakAllWebsitesVarnishCache extends OrgAction
{
    use WithVarnishBan;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo('sysadmin.edit');
    }

    /**
     * Bans every storefront object on every Varnish host over HTTP: litio and boro each run their
     * own Varnish, and restart_varnish.sh only reaches the one on the host it runs on.
     */
    public function handle(?Command $command = null): array
    {
        $this->sendVarnishBanHttp(['x-ban-all' => '1'], $command);

        return [];
    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisationFromGroup(group(), $request);
        $result = $this->handle();
        $this->purgeCloudflare();

        return $result;
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
        $this->purgeCloudflare();

        return 0;
    }

    protected function purgeCloudflare(): void
    {
        Website::where('status', true)->get()->each(PurgeCloudflareWebsitePages::purgeNow(...));
    }

}
