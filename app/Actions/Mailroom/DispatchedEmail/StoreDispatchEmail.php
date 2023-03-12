<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 10 Mar 2023 20:59:01 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Mailroom\DispatchedEmail;

use App\Actions\Mailroom\EmailAddress\GetEmailAddress;
use App\Models\Mailroom\DispatchedEmail;
use App\Models\Mailroom\Mailshot;
use App\Models\Mailroom\Outbox;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreDispatchEmail
{
    use AsAction;

    public function handle(Outbox|Mailshot $parent, string $email, array $modelData): DispatchedEmail
    {
        if (class_basename($parent)=='Mailshot') {
            $modelData['outbox_id']=$parent->outbox_id;
        }

        $emailAddress                 =GetEmailAddress::run($email);
        $modelData['email_address_id']=$emailAddress->id;
        /** @var DispatchedEmail $dispatchedEmail */
        $dispatchedEmail= $parent->dispatchedEmails()->create($modelData);
        return $dispatchedEmail;
    }
}
