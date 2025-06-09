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

        if (!$disk->exists($baseDir)) {
            $disk->makeDirectory($baseDir);
        }
        $sitemap = Sitemap::create();
        foreach ($website->webpages as $webpage) {
            $sitemap->add(Url::create($webpage->getUrl())
                ->setLastModificationDate($webpage->updated_at));
        }
        $sitemap->writeToDisk('local', 'sitemaps/sitemap-' . $website->id  . '.xml');
    }

    public string $commandSignature = 'website_sitemap';

    public function asCommand($command)
    {
        /** @var Website $w */
        $w = Website::find(11);

        $this->handle($w);

    }
}
