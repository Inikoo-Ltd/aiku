<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 24 Oct 2025 08:22:09 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\Luigi;

use App\Models\Web\Website;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteReindexWebsiteLuigiData
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
