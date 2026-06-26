<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 14 May 2026 13:04:50 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Crawl;

use App\Enums\Web\Crawl\CrawlStateEnum;
use App\Models\Web\Crawl;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class PurgeStaleCrawls
{
    use AsAction;

    public function handle(?Command $command = null): void
    {
        foreach (Crawl::where('running', true)->where('updated_at', '<', now()->subMinutes(10))->get() as $crawl) {
            $command?->info("Stopping crawl: ID $crawl->id) ");
            $crawl->update(
                [
                    'running'       => false,
                    'state'         => CrawlStateEnum::FINISH,
                    'finish_reason' => 'stale',
                    'end_at'        => now()
                ]
            );
        }
    }

    public function getCommandSignature(): string
    {
        return 'crawl:purge';
    }

    public function asCommand(Command $command): int
    {
        $this->handle($command);

        return 0;
    }

}
