<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 24 Oct 2025 08:21:48 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\Luigi;

use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Models\Web\Website;
use Exception;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexWebsiteLuigi
{
    use AsAction;
    use WithLuigis;

    public string $commandSignature = 'luigis:reindex_website {website?}';

    /**
     * @throws \Exception
     */
    public function handle(Website $website): void
    {
        $accessToken = $this->getAccessToken($website);
        if (count($accessToken) < 2) {
            Log::error('Luigi\'s Box access token is not configured properly');

            return;
        }

        $website->webpages()
            ->with('model')
            ->where('state', 'live')
            ->whereIn('type', [WebpageTypeEnum::CATALOGUE, WebpageTypeEnum::BLOG])
            ->whereIn('model_type', ['Product', 'ProductCategory', 'Collection'])
            ->chunk(1000, function ($webpages) use ($website) {
                $objects = [];
                foreach ($webpages as $webpage) {
                    $object = $this->getObjectFromWebpage($webpage);
                    if ($object) {
                        $objects[] = $object;
                    }
                }

                $body       = [
                    'objects' => $objects
                ];
                $compressed = count($objects) >= 1000;
                try {
                    $this->request($website, '/v1/content', $body, 'post', $compressed);
                } catch (Exception $e) {
                    print "Failed to reindex website $website->domain: ".$e->getMessage()."\n";

                    return;
                }
            });
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
