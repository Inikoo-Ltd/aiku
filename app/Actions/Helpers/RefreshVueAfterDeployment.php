<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 29 Jul 2025 10:06:49 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers;

use App\Events\AppVersionWebsocketEvent;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RefreshVueAfterDeployment
{
    use AsAction;

    public function getCommandSignature(): string
    {
        return 'deploy:refresh_vue';
    }

    public function asCommand(Command $command): void
    {
        AppVersionWebsocketEvent::dispatch();
        $command->info('Refresh vue.');
    }
}
