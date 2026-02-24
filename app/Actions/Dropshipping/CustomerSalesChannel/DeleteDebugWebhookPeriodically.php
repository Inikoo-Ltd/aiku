<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 30 Aug 2022 13:05:43 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */

namespace App\Actions\Dropshipping\CustomerSalesChannel;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\DebugWebhooks;
use Illuminate\Console\Command;

class DeleteDebugWebhookPeriodically extends OrgAction
{
    use WithActionUpdate;

    public $commandSignature = 'delete:debug-webhook {days}';

    public function handle(int $days): void
    {
        DebugWebhooks::where('created_at', '<', now()->subDays($days))->delete();
    }
    public function asCommand(Command $command): void
    {
        $this->handle($command->argument('days'));
    }
}
