<?php

/** @noinspection PhpUnused */

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 29 Jul 2025 10:06:49 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers;

use App\Events\AppVersionWebsocketEvent;
use App\Models\DevOps\AppDeployment;
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
        AppVersionWebsocketEvent::dispatch(AppDeployment::orderByDesc('id')->first());
        $command->info('Refresh vue.');
    }
}
