<?php

/*
 * Author: Vika Aqordi
 * Created on 11-12-2025-09h-25m
 * Github: https://github.com/aqordeon
 * Copyright: 2025
*/

namespace App\Actions\Web\Webpage\Luigi;

use App\Actions\Web\Website\Luigi\WithLuigis;
use App\Models\Web\Website;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteLuigiContent
{
    use AsAction;
    use WithLuigis;

    public string $commandSignature = 'luigis:remove_luigi_content {--website_id=} {--type=} {--identity=}';

    /**
     * @throws \Exception
     */
    public function handle(Website $website, string $identity, string $type): void
    {
        $this->deleteContentFromLuigi($website, $identity, $type);
    }

    /**
     * @throws \Laravel\Octane\Exceptions\DdException
     * @throws \Illuminate\Http\Client\ConnectionException
     * @throws \Exception
     */
    public function asCommand($command): int
    {
        $websiteId = $command->option('website_id');
        $type      = $command->option('type');
        $identity  = $command->option('identity');

        $website = Website::find($websiteId);
        
        $this->handle($website, $identity, $type);

        return 0;
    }
}
