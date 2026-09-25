<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 21 Apr 2025 11:08:16 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\FetchStack;

use App\Actions\Traits\WithOrganisationSource;
use App\Actions\Transfers\Aurora\Api\ProcessAuroraOrgStockMovement;
use App\Enums\Transfers\FetchStack\FetchStackStateEnum;
use App\Models\Transfers\FetchStack;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessFetchStack
{
    use AsAction;
    use WithOrganisationSource;


    /**
     * @throws \Throwable
     */
    public function handle(FetchStack $fetchStack, $bg = false): void
    {
        $organisation = $fetchStack->organisation;
        $modelData    = [
            'fetch_stack_id' => $fetchStack->id,
            'id'             => $fetchStack->operation_id,
            'bg'             => $bg
        ];

        $fetchStack->update([
            'start_fetch_at' => now()
        ]);


        if ($fetchStack->operation != 'OrgStockMovement') {
            $fetchStack->update(['state' => FetchStackStateEnum::IGNORED]);

            return;
        }

        $res = ProcessAuroraOrgStockMovement::make()->action($organisation, $modelData);

        if ($res !== null) {
            // A refused fetcher marks itself IGNORED from inside the job, which can land
            // before this line does. Writing PROCESSING over it would strand the row:
            // nothing ever revisits PROCESSING.
            FetchStack::where('id', $fetchStack->id)
                ->where('state', '!=', FetchStackStateEnum::IGNORED)
                ->update([
                    'state'  => $bg ? FetchStackStateEnum::PROCESSING : FetchStackStateEnum::SUCCESS,
                    'result' => $res,
                ]);

            if (!$bg) {
                $fetchStack->update(
                    [
                        'finish_fetch_at' => now()
                    ]
                );
            }
        } else {
            $fetchStack->update([
                'send_to_queue_at' => null,
                'start_fetch_at'   => null,
                'state'            => FetchStackStateEnum::IN_PROCESS,
            ]);
        }
    }


}
