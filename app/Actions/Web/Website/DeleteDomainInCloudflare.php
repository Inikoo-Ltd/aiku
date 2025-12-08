<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 27 Jun 2023 08:35:28 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website;

use App\Actions\Web\Website\Utils\DestroyDomainCloudflare;
use App\Models\Web\Website;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteDomainInCloudflare
{
    use AsAction;

    public string $commandSignature = 'website:delete {website}';

    public string $commandDescription = 'Remove website from Cloudflare';

    public function handle(Website $website): string
    {
        DestroyDomainCloudflare::run($website->cloudflare_id);

        $website->update([
            'cloudflare_id' => null,
            'cloudflare_status' => null,
        ]);

        return $website;
    }

    public function asCommand(Command $command): string
    {
        $website = Website::where('website', $command->argument('website'))->first();

        return $this->handle($website);
    }
}
