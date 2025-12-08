<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 27 Jun 2023 08:35:28 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website;

use App\Actions\Web\Website\Utils\RegisterDomainCloudflare;
use App\Models\Web\Website;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class AddWebsiteToCloudflare
{
    use AsAction;

    public string $commandSignature = 'website:add-cloudflare {website}';

    public string $commandDescription = 'Register website to Cloudflare';

    public function handle(Website $website): string
    {
        $response = RegisterDomainCloudFlare::run($website->domain);

        $website->update([
            'cloudflare_id' => $response['result']['id'],
            'cloudflare_status' => $response['result']['status'],
        ]);

        return $website;
    }

    public function asCommand(Command $command): int
    {
        try {
            $website = Website::where('slug', $command->argument('website'))->firstOrFail();
        } catch (Exception) {
            $command->error('Website not found');

            return 1;
        }

        $this->handle($website);

        return 0;
    }
}
