<?php

namespace App\Actions\Dropshipping\Platform;

use App\Actions\OrgAction;
use App\Models\Dropshipping\Platform;
use Illuminate\Console\Command;

class RepairPlatformSalesIntervals extends OrgAction
{
    public string $commandSignature = 'repair:platform_sales_intervals';

    public function asCommand(Command $command): void
    {
        foreach (Platform::all() as $platform) {
            if ($platform->salesIntervals) {
                continue;
            }

            $platform->salesIntervals()->create();
        }
    }
}
