<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 21 Apr 2025 11:08:16 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\FetchStack;

use App\Enums\Transfers\FetchStack\FetchStackStateEnum;
use App\Models\Transfers\FetchStack;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessFetchStacks
{
    use AsAction;


    /**
     * @throws \Exception
     */
    public function handle(): void
    {

        StoreFetchStacks::run();

        foreach (FetchStack::where('state', FetchStackStateEnum::IN_PROCESS)->orderBy('submitted_at')->get() as $fetchStack) {
            $fetchStack->update([
                'state'            => FetchStackStateEnum::SEND_TO_QUEUE,
                'send_to_queue_at' => now()
            ]);
            ProcessFetchStack::run($fetchStack, true);
        }
    }


    public function getCommandSignature(): string
    {
        return 'fetch_stacks:process';
    }

    /**
     * @throws \Exception
     */
    public function asCommand(): int
    {
        $this->handle();

        return 0;
    }
}
