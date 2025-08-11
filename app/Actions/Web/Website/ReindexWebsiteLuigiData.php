<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 22-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Web\Website;

use App\Actions\Web\WithLuigis;
use App\Models\Web\Website;
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexWebsiteLuigiData
{
    use AsAction;
    use WithLuigis;

    public string $commandSignature = 'luigis:reindex_website {website?}';

    /**
     * @throws \Exception
     */
    public function handle(Website $website): void
    {
        $this->reindex($website);
    }

    public function asController(Website $website): void
    {
        $this->handle($website);
    }

    /**
     * @throws \Laravel\Octane\Exceptions\DdException
     * @throws \Illuminate\Http\Client\ConnectionException
     * @throws \Exception
     */
    public function asCommand($command): int
    {
        if ($command->argument('website')) {
            $website = Website::find($command->argument('website'));
        } else {
            $website = Website::first();
        }
        $this->handle($website);
        return 0;
    }
}
