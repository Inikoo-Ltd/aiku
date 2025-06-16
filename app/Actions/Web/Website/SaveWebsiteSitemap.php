<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 09-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Web\Website;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Web\Website;
use Illuminate\Support\Facades\Storage;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class SaveWebsiteSitemap extends OrgAction
{
    use WithActionUpdate;

    public function handle(Website $website)
    {
        $baseDir = 'sitemaps';
        $disk    = Storage::disk('local');
        $limit   = 50000; // limit from Google is 50,000 URLs per sitemap
        $chunkSize = 100;

        if (!$disk->exists($baseDir)) {
            $disk->makeDirectory($baseDir);
        }

        $sitemap = Sitemap::create();
        $count = 0;

        $website->webpages()->chunk($chunkSize, function ($webpages) use (&$sitemap, &$count, $limit, $website) {
            foreach ($webpages as $webpage) {
                $sitemap->add(Url::create($webpage->getUrl())
                    ->setLastModificationDate($webpage->updated_at));

                $count++;

                if ($count >= $limit) {
                    throw new \Exception("Sitemap limit of {$limit} URLs reached for website ID {$website->id}");
                }
            }
        });

        $sitemap->writeToDisk('local', "sitemaps/sitemap-{$website->id}.xml");
    }

    public string $commandSignature = 'website_sitemap';

    public function asCommand($command)
    {
        /** @var Website $w */
        $w = Website::find(14);

        $this->handle($w);

    }
}
