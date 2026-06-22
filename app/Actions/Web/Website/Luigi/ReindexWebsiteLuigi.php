<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 24 Oct 2025 08:21:48 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\Luigi;

use App\Actions\Web\Webpage\Luigi\ReindexWebpageLuigi;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Models\Web\Website;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexWebsiteLuigi implements ShouldBeUnique
{
    use AsAction;
    use WithLuigis;

    public string $jobQueue = 'default-long-slave';

    public string $commandSignature = 'luigis:reindex_website {website?}';
    public int $jobTries = 1;

    public function getJobUniqueId(Website $website): string
    {
        return $website->id;
    }

    /**
     * @throws \Exception
     */
    public function handle(Website $website, ?Command $command = null): void
    {
        Log::info('Running ReindexWebsiteLuigi');
        $command?->info("Reindexing website ".$website->domain);

        $accessToken = $this->getAccessToken($website);
        if (count($accessToken) < 2) {
            Log::error('Luigis Box access token is not configured properly');
            $command?->error("Luigis Box access token is not configured properly");

            return;
        }

        $command?->info("Starting reindexing");


        $website->webpages()
            ->with('model')
            ->where('state', 'live')
            ->whereIn('type', [WebpageTypeEnum::CATALOGUE, WebpageTypeEnum::BLOG])
            ->whereIn('model_type', ['Product', 'ProductCategory', 'Collection'])
            ->chunk(200, function ($webpages) use ($website, $command) {
                if ($command) {
                    $objects = [];
                    $index=0;
                    foreach ($webpages as $webpage) {
                        $command->line($index++.' '.$webpage->slug);
                        $object = $this->getObjectFromWebpage($webpage);
                        if ($object) {
                            $objects[] = $object;
                        }
                    }

                    $body       = [
                        'objects' => $objects
                    ];
                    //$compressed = count($objects) >= 200;
                    $compressed = false; // if not set as null, it fails, so do not change this
                    $command->info("Reindexing webpages $website->domain with ".count($objects)." objects");
                    try {
                        $this->request($website, '/v1/content', $body, 'post', $compressed);
                        $command->line("Success to reindex ".count($objects));
                    } catch (Exception $e) {
                        $command->error("Failed to reindex website $website->domain: ".$e->getMessage());


                        return;
                    }
                } else {
                    foreach ($webpages as $webpage) {
                        ReindexWebpageLuigi::dispatch($webpage)->delay(1);
                    }
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
            $website = Website::where('slug', $command->argument('website'))->firstOrFail();
            $this->handle($website, $command);
        } else {
            foreach (Website::where('migrated', true) as $website) {
                $this->handle($website, $command);
            }
        }


        return 0;
    }
}
