<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Jun 2023 15:06:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Notifications;

use App\Models\SysAdmin\User;
use App\Notifications\MeasurementShareNotification;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\AsObject;

class PublishNotification
{
    use AsObject;
    use AsAction;

    public string $commandSignature   = 'notification:publish';
    public string $commandDescription = 'Publish push notification';

    public function handle(User $user, $content, $target = ['fcm']): void
    {
        if (in_array('fcm', $target)) {
            $user->notify(new MeasurementShareNotification($content));
        }

        if (in_array('mail', $target)) {
            $user->notify();
        }
    }

    public function asCommand(): void
    {
        $user    = User::where('username', 'aiku')->first();
        $content = [
            'title' => 'Subject/Title',
            'body'  => 'Hello'
        ];

        $this->handle($user, $content);
    }
}
