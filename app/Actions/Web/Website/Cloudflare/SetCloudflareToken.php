<?php

/** @noinspection PhpUnused */

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Jun 2026 14:02:04 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\Cloudflare;

use App\Enums\Web\Website\WebsiteCloudflareStatusEnum;
use App\Models\Web\Website;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class SetCloudflareToken
{
    use asAction;


    public function handle(Website $website, string $token, ?Command $command = null): void
    {
        $zoneId = GetCloudflareZoneId::run(website: $website, token: $token);

        if ($zoneId) {
            $encryptedToken = encrypt($token);
            $website->update(
                [
                    'cloudflare_token'   => $encryptedToken,
                    'cloudflare_zone_id' => $zoneId,
                    'cloudflare_status'  => WebsiteCloudflareStatusEnum::ACTIVE
                ]
            );
            $command?->info('Cloudflare token set');
        } else {
            $command?->warn('Cloudflare zone not found / Invalid token');
        }
    }

    public function getCommandSignature(): string
    {
        return 'cloudflare:set-token {token} {website}';
    }

    public function asCommand(Command $command): void
    {
        $website = Website::where('slug', $command->argument('website'))->firstOrFail();
        $this->handle($website, $command->argument('token'), $command);
    }
}
