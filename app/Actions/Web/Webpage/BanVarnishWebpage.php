<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Oct 2025 19:32:46 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebEditAuthorisation;
use App\Actions\Web\Website\Cloudflare\PurgeCloudflareWebsitePages;
use App\Models\Web\Webpage;
use Illuminate\Console\Command;
use Lorisleiva\Actions\ActionRequest;
use App\Actions\Traits\WithVarnishBan;

class BanVarnishWebpage extends OrgAction
{
    use WithVarnishBan;
    use WithWebEditAuthorisation;

    /**
     * Logged-out visitors may see a price or stock up to a day old, so a ban for those alone leaves the Cloudflare copy: $purgeEdge false.
     */
    public function handle(Webpage $webpage, ?Command $command = null, bool $purgeEdge = true): void
    {
        $this->sendVarnishBanHttp(
            [
                'x-ban-webpage' => $webpage->id,
            ],
            $command
        );

        if ($purgeEdge) {
            PurgeCloudflareWebsitePages::schedule($webpage->website);
        }
    }

    public function asController(Webpage $webpage, ActionRequest $request): array
    {
        $this->initialisationFromShop($webpage->shop, $request);

        $this->handle($webpage);
        PurgeCloudflareWebsitePages::purgeNow($webpage->website);

        return [
            'state' => 'success'
        ];
    }


    public function getCommandSignature(): string
    {
        return 'varnish:ban:webpage {slug}';
    }

    public function asCommand(Command $command): int
    {
        $webpage = Webpage::where('slug', $command->argument('slug'))->first();
        $this->handle($webpage, $command);
        PurgeCloudflareWebsitePages::purgeNow($webpage->website);

        return 0;
    }

}
