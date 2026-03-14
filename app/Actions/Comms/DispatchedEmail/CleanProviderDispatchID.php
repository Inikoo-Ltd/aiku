<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 14 Mar 2026 15:22:20 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\DispatchedEmail;

use App\Enums\Comms\DispatchedEmail\DispatchedEmailProviderEnum;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CleanProviderDispatchID
{
    use AsAction;

    public function handle(?Command $command = null): void
    {
        $batchSize = 5000;
        $affected  = 1;

        while ($affected > 0) {
            $affected = DB::table('dispatched_emails')
                ->whereIn('id', function ($query) use ($batchSize) {
                    $query->select('id')
                        ->from('dispatched_emails')
                        ->whereNotNull('provider_dispatch_id')
                        ->where(function ($q) {
                            $q->where(function ($sq) {
                                $sq->where('provider', DispatchedEmailProviderEnum::SES->value)
                                    ->where('sent_at', '<', now()->subDays(61));
                            })->orWhere(function ($sq) {
                                $sq->where('provider', '!=', DispatchedEmailProviderEnum::SES->value)
                                    ->where('sent_at', '<', now()->subDays(7));
                            });
                        })
                        ->limit($batchSize);
                })
                ->update(['provider_dispatch_id' => null]);
            $command?->info("Updated $affected dispatched emails");
        }
    }

    public function getCommandSignature(): string
    {
        return 'dispatched-email:clean-provider-dispatch-id';
    }

    public function asCommand(Command $command): void
    {
        $this->handle($command);
    }

}
