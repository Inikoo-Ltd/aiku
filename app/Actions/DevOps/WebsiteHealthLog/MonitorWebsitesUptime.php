<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 29 Jun 2026 23:12:33 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\DevOps\WebsiteHealthLog;

use App\Models\Web\Website;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class MonitorWebsitesUptime
{
    use AsAction;


    public function handle(?Command $command = null): int
    {
        $urls = [];
        /** @var Website $website */
        foreach (
            Website::where('migrated', true)
                ->where('status', true)
                ->get() as $website
        ) {
            $urls[] = $website->storefront->canonical_url;
        }


        foreach ($urls as $url) {
            $result = MonitorWebpageUptime::run($url);
            if (!$result['is_up']) {
                $command?->error("Website $url is down");
            } else {
                $command?->info("Website $url is up");
            }
        }

        return 0;
    }

    public function getCommandSignature(): string
    {
        return 'monitor:websites';
    }

    public function getCommandDescription(): string
    {
        return 'Monitor websites homepage uptime and notify Discord on failure';
    }

    public function asCommand(Command $command): int
    {
        $this->handle($command);
        return 0;
    }






}
