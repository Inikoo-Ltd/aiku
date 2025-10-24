<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 24 Oct 2025 08:23:08 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage\Luigi;

use App\Actions\Web\Website\Luigi\WithLuigis;
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
