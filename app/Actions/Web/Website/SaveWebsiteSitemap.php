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
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Web\Website;
use Illuminate\Support\Facades\Storage;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class SaveWebsiteSitemap extends OrgAction
{
    use WithActionUpdate;

    public function handle(Website $website): int
    {
        $baseDir   = 'sitemaps';
        $disk      = Storage::disk('local');
        $limit     = 50000; // limit from Google is 50,000 URLs per sitemap
        $chunkSize = 100;

        if (!$disk->exists($baseDir)) {
            $disk->makeDirectory($baseDir);
        }

        $sitemap = Sitemap::create();
        $count   = 0;

        $website->webpages()->with('liveSnapshot')->where('state', WebpageStateEnum::LIVE)->chunk($chunkSize, function ($webpages) use (&$sitemap, &$count, $limit) {
            foreach ($webpages as $webpage) {

                if ($webpage?->liveSnapshot?->published_at) {
                    $sitemap->add(Url::create($webpage->getUrl(true))
                        ->setLastModificationDate($webpage->liveSnapshot->published_at));
                } else {
                    $sitemap->add(Url::create($webpage->getUrl(true)));
                }

                $count++;

                if ($count >= $limit) {
                    break;
                }
            }
        });

        $sitemap->writeToDisk('local', "sitemaps/sitemap-$website->id.xml");

        return $count;
    }

    public string $commandSignature = 'website_sitemap';

    public function asCommand(): void
    {
        /** @var Website $w */
        $w = Website::find(14);

        $this->handle($w);
    }
}
