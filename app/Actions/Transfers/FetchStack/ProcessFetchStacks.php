<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 21 Apr 2025 11:08:16 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\FetchStack;

use App\Enums\Transfers\FetchStack\FetchStackStateEnum;
use App\Models\Transfers\FetchStack;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessFetchStacks
{
    use AsAction;


    /**
     * @throws \Exception
     */
    public function handle(bool $runInBackground = true, Command $command = null): void
    {
        StoreFetchStacks::run();


        $query = FetchStack::where('state', FetchStackStateEnum::IN_PROCESS)
            ->orderBy('submitted_at');
        $query->limit($runInBackground ? 1000 : 100000);

        /** @var FetchStack $fetchStack */
        foreach ($query->get() as $fetchStack) {
            $fetchStack->update([
                'state'            => FetchStackStateEnum::SEND_TO_QUEUE,
                'send_to_queue_at' => now()
            ]);

            $command?->info("Processing: $fetchStack->id $fetchStack->operation $fetchStack->operation_id $fetchStack->submitted_at ");

            ProcessFetchStack::run($fetchStack, $runInBackground);
        }
    }


    public function getCommandSignature(): string
    {
        return 'fetch_stacks:process {background?}';
    }

    /**
     * @throws \Exception
     */
    public function asCommand(Command $command): int
    {
        $processInBackground = $command->argument('background') ?? true;

        $this->handle($processInBackground, $command);

        return 0;
    }
}
