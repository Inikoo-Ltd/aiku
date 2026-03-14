<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 14 Mar 2026 15:22:20 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\DispatchedEmail;

use App\Enums\Comms\DispatchedEmail\DispatchedEmailProviderEnum;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CleanProviderDispatchID
{
    use AsAction;

    public function handle(): void
    {
        DB::table('dispatched_emails')
            ->where('provider', DispatchedEmailProviderEnum::SES->value)
            ->where('created_at', '<', now()->subDays(1))
            ->update(['provider_dispatch_id' => null]);

        DB::table('dispatched_emails')
            ->where('provider','!=' ,DispatchedEmailProviderEnum::SES->value)
            ->where('created_at', '<', now()->subDays(3))
            ->update(['provider_dispatch_id' => null]);
    }

    public function getCommandSignature(): string
    {
        return 'dispatched-email:clean-provider-dispatch-id';
    }

    public function asCommand(): void
    {
        $this->handle();
    }

}
