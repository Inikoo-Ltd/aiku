<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 04-07-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Web\Webpage;

use App\Actions\Web\WithLuigis;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteReindexWebpageLuigiData
{
    use AsAction;
    use WithLuigis;

    public string $commandSignature = 'luigis:remove_reindex_webpage {webpage?}';

    /**
     * @throws \Exception
     */
    public function handle(Webpage $webpage): void
    {
        $this->deleteContentFromWebpage($webpage);
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
