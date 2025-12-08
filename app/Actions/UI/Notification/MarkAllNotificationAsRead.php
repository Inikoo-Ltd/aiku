<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Apr 2023 20:22:54 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Notification;

use App\Actions\Traits\WithActionUpdate;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;

class MarkAllNotificationAsRead
{
    use WithActionUpdate;

    private bool $asAction = false;

    public function handle(User $user): void
    {
        $user->notifications->markAsRead();
    }

    public function asController(ActionRequest $request): void
    {
        $user = $request->user();

        $this->handle($user);
    }
}
