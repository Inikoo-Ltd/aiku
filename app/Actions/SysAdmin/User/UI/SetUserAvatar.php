<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:24:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User\UI;

use App\Actions\Traits\WithSetUserableAvatar;
use App\Models\SysAdmin\User;
use Exception;
use Illuminate\Console\Command;

class SetUserAvatar
{
    use WithSetUserableAvatar;


    public string $commandSignature = 'user:avatar {slug : User slug}';

    public function asCommand(Command $command): int
    {
        try {
            $user = User::where('slug', $command->argument('slug'))->firstOrFail();
        } catch (Exception) {
            $command->error('User not found');

            return 1;
        }

        $result = $this->handle($user);

        if ($result['result'] === 'success') {
            $command->info('Avatar set');

            return 0;
        } else {
            $command->error($result['message']);

            return 1;
        }
    }
}
