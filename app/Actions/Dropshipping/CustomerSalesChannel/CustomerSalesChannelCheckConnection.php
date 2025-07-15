<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 11 Jul 2025 15:18:19 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\CustomerSalesChannel;

use App\Actions\OrgAction;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsCommand;

class CustomerSalesChannelCheckConnection extends OrgAction
{
    use AsCommand;

    public string $commandSignature = 'channel:check-connection {customerSalesChannel}';

    public function handle(CustomerSalesChannel $customerSalesChannel): bool
    {
        return (bool) $customerSalesChannel->user?->checkConnection();
    }

    public function asCommand(Command $command): void
    {
        $customerSalesChannel = CustomerSalesChannel::find($command->argument('customerSalesChannel'));

        $this->handle($customerSalesChannel);
    }
}
