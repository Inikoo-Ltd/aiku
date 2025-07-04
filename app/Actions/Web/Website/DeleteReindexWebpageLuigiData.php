<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 04-07-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Web\Website;

use App\Actions\Web\WithLuigis;
use App\Models\Web\Website;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteReindexWebpageLuigiData
{
    use AsAction;
    use WithLuigis;

    public string $commandSignature = 'luigis:remove_reindex_website {website?}';

    /**
     * @throws \Exception
     */
    public function handle(Website $website): void
    {
        $this->deleteContentFromWebsite($website);
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
