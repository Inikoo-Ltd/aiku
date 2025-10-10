<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 03-07-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Web\Webpage;

use App\Actions\Web\WithLuigis;
use App\Models\Web\Webpage;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexWebpageLuigiData implements ShouldBeUnique
{
    use AsAction;
    use WithLuigis;

    public string $jobQueue = 'low-priority';

    public function getJobUniqueId(Webpage $webpage): string
    {
        return $webpage->id;
    }


    public string $commandSignature = 'luigis:reindex_webpage {webpage?}';

    /**
     * @throws \Exception
     */
    public function handle(Webpage $webpage): void
    {
        $this->reindex($webpage);
    }

    /**
     * @throws \Laravel\Octane\Exceptions\DdException
     * @throws \Illuminate\Http\Client\ConnectionException
     * @throws \Exception
     */
    public function asCommand($command): int
    {
        if ($command->argument('webpage')) {
            $webpage = Webpage::find($command->argument('webpage'));
        } else {
            $webpage = Webpage::first();
        }
        $this->handle($webpage);
        return 0;
    }
}
