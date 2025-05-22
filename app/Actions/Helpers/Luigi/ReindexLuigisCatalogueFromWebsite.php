<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 22-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Helpers\Luigi;

use App\Actions\Helpers\Luigi\Trait\WithLuigis;
use App\Models\Web\Website;
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexLuigisCatalogueFromWebsite
{
    use AsAction;
    use WithLuigis;

    public string $commandSignature = 'luigis:reindex_catalogue {website?}';

    public function handle(Website $website)
    {
        $this->reindex($website);
    }

    /**
     * @throws \Laravel\Octane\Exceptions\DdException
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function asCommand($command)
    {
        if ($command->argument('website')) {
            $website = Website::find($command->argument('website'));
        } else {
            $website = Website::first();
        }
        $this->handle($website);
    }
}
