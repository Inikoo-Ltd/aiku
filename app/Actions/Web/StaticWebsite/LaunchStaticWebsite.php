<?php

/*
 * Author: Rifqi Taufiqurrohman
 * Created: Thu, 12 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\StaticWebsite;

use App\Enums\Web\StaticWebsite\StaticWebsiteStateEnum;
use App\Models\Web\StaticWebsite;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

/**
 * Lets Aiku answer the info website's domain. Run it before the domain's nameservers are moved to
 * Cloudflare, so the first visitor routed to Aiku already gets the site. Refused while verification
 * still finds anything pointing at the old host.
 */
class LaunchStaticWebsite
{
    use AsAction;

    public string $commandSignature = 'static_website:launch {domain : Domain of the info website, e.g. aw-freedom.com}';

    public function handle(StaticWebsite $staticWebsite): StaticWebsite
    {
        $verification = VerifyStaticWebsite::run($staticWebsite);

        if ($verification['pages'] === 0 || $verification['forbidden_references'] || $verification['missing_assets']) {
            throw new RuntimeException("$staticWebsite->domain is not ready: run static_website:verify $staticWebsite->domain");
        }

        $staticWebsite->update(['state' => StaticWebsiteStateEnum::LIVE]);

        return $staticWebsite;
    }

    public function asCommand(Command $command): int
    {
        $staticWebsite = StaticWebsite::where('domain', StoreStaticWebsite::normaliseDomain($command->argument('domain')))->first();
        if (!$staticWebsite) {
            $command->error("No info website for {$command->argument('domain')}");

            return 1;
        }

        try {
            $this->handle($staticWebsite);
        } catch (RuntimeException $e) {
            $command->error($e->getMessage());

            return 1;
        }

        $command->info("$staticWebsite->domain is live in Aiku: requests reaching Aiku for it are now answered from the stored pages.");

        return 0;
    }
}
