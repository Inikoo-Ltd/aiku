<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 00:30:00 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\WebVital;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class PruneWebVitalSamples
{
    use AsAction;

    public const int RETENTION_DAYS = 400;

    public function handle(): int
    {
        return DB::table('web_vital_samples')->where('created_at', '<', now()->subDays(self::RETENTION_DAYS))->delete();
    }

    public function getCommandSignature(): string
    {
        return 'maintenance:prune_web_vital_samples';
    }

    public function asCommand(Command $command): int
    {
        $command->info('Deleted '.$this->handle().' web vital samples');

        return 0;
    }
}
