<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\EmailAddress;

use App\Models\Comms\EmailAddress;
use App\Models\SysAdmin\Group;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreEmailAddress
{
    use AsAction;

    public function handle(Group $group, string $email): EmailAddress
    {
        /** @var EmailAddress $emailAddress */
        $emailAddress = $group->emailAddresses()->where('email', $email)->first();
        if (!$emailAddress) {
            $emailAddress = $group->emailAddresses()->create(
                [
                    'email' => $email
                ]
            );
        }

        return $emailAddress;
    }
}
