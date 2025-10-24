<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 24 Oct 2025 08:23:05 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage\Luigi;

use App\Actions\OrgAction;
use App\Actions\Web\Website\Luigi\WithLuigis;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Models\Web\Webpage;
use Exception;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexWebpageLuigiData extends OrgAction implements ShouldBeUnique
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
        $accessToken = $this->getAccessToken($webpage->website);
        if (count($accessToken) < 2) {
            Log::error('Luigi\'s Box access token is not configured properly');

            return;
        }


        if ($webpage->type != WebpageTypeEnum::CATALOGUE && $webpage->type != WebpageTypeEnum::BLOG) {
            return;
        }

        $objects[] = $this->getObjectFromWebpage($webpage);

        $body = [
            'objects' => $objects
        ];
        try {
            $this->request($webpage, '/v1/content', $body);
        } catch (Exception $e) {
            Log::error("Failed to reindex webpage $webpage->title: ".$e->getMessage());
        }
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
