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
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexWebpageLuigiData extends OrgAction implements ShouldBeUnique
{
    use AsAction;
    use WithLuigis;

    public string $jobQueue = 'urgent';

    public function getJobUniqueId(int|null $webpageId): string
    {
        return $webpageId ?? 'string';
    }


    public string $commandSignature = 'luigis:reindex_webpage {webpage?}';

    /**
     * @throws \Exception
     */
    public function handle(int|null $webpageId): array
    {
        if ($webpageId == null) {
            return [
                'status'  => 'error',
                'message' => 'Webpage ID is required.'
            ];
        }
        $webpage = Webpage::find($webpageId);
        if (!$webpage) {
            return [
                'status'  => 'error',
                'message' => 'Webpage not found.'
            ];
        }

        $accessToken = $this->getAccessToken($webpage->website);
        if (count($accessToken) < 2) {
            return [
                'status'  => 'error',
                'message' => 'No access token found. Neither tracker ID nor private key.'
            ];
        }


        if ($webpage->type != WebpageTypeEnum::CATALOGUE && $webpage->type != WebpageTypeEnum::BLOG) {
            return [
                'status'  => 'error',
                'message' => 'Webpage type is not supported.'
            ];
        }

        $objects[] = $this->getObjectFromWebpage($webpage);

        $body = [
            'objects' => $objects
        ];
        try {
            $return = $this->request($webpage, '/v1/content', $body);

            return [
                'status'  => 'success',
                'message' => 'Webpage reindexed successfully.',
                'data'    => $return
            ];
        } catch (Exception $e) {
            return [
                'status'  => 'error',
                'message' => $e->getMessage()
            ];
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
        $this->handle($webpage->id);

        return 0;
    }
}
