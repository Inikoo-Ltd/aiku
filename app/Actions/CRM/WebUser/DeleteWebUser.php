<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:25:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\WebUser;

use App\Actions\CRM\Customer\Hydrators\CustomerHydrateWebUsers;
use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\WebUser;
use Illuminate\Console\Command;

class DeleteWebUser
{
    use WithActionUpdate;

    public string $commandSignature = 'delete:web-user  {web_user}';

    public function handle(WebUser $webUser, array $deletedData = [], bool $skipHydrate = false): WebUser
    {
        $webUser->delete();
        $webUser = $this->update($webUser, $deletedData, ['data']);

        if (!$skipHydrate) {
            CustomerHydrateWebUsers::dispatch($webUser->customer);
        }
        return $webUser;
    }

    public function asCommand(Command $command): int
    {
        try {
            $webUser = WebUser::withTrashed()->findOrFail($command->argument('web_user'));
            $this->handle($webUser);
            $command->info('Web User deleted');
            return 0;
        } catch (\Exception $e) {
            $command->error($e->getMessage());
            return 1;
        }
    }
}
