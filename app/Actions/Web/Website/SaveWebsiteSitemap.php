<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 09-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Web\Website;


use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class SaveWebsiteSitemap implements ShouldBeUnique
{

    use AsAction;

    public int $jobTries = 1;
    public string $jobQueue = 'low-priority';

    public function getJobUniqueId(Website $website): string
    {
        return $website->id;
    }

    public function handle(Website $website, Command|null $command = null): int
    {
        $baseDir   = 'sitemaps';
        $disk      = Storage::disk('local');
        $chunkSize = 100;

        if (!$disk->exists($baseDir)) {
            $disk->makeDirectory($baseDir);
        }

        $groups = [
            "products"        => Sitemap::create(),
            "departments"     => Sitemap::create(),
            "sub_departments" => Sitemap::create(),
            "families"        => Sitemap::create(),
            "contents"        => Sitemap::create(),
            "blogs"           => Sitemap::create(),
            "pages"           => Sitemap::create(),
            "collections"     => Sitemap::create(),
        ];

        $groupsCount = [
            "products"        => 0,
            "departments"     => 0,
            "sub_departments" => 0,
            "families"        => 0,
            "contents"        => 0,
            "blogs"           => 0,
            "pages"           => 0,
            "collections"     => 0,
        ];

        $count   = 0;
        $scheme  = app()->environment('production') ? 'https' : 'http';
        $baseUrl = $scheme.'://'.$website->domain;

        $website->webpages()->with('liveSnapshot')->where('state', WebpageStateEnum::LIVE)->chunk($chunkSize, function ($webpages) use (&$count, &$groups, &$groupsCount) {
            $map   = [
                WebpageSubTypeEnum::PRODUCT->value              => "products",
                WebpageSubTypeEnum::PRODUCTS->value             => "products",
                WebpageSubTypeEnum::CATALOGUE->value            => "products",
                WebpageSubTypeEnum::DEPARTMENT->value           => "departments",
                WebpageSubTypeEnum::SUB_DEPARTMENT->value       => "sub_departments",
                WebpageSubTypeEnum::FAMILY->value               => "families",
                WebpageSubTypeEnum::CONTENT->value              => "contents",
                WebpageSubTypeEnum::ABOUT_US->value             => "contents",
                WebpageSubTypeEnum::CONTACT->value              => "contents",
                WebpageSubTypeEnum::RETURNS->value              => "contents",
                WebpageSubTypeEnum::SHIPPING->value             => "contents",
                WebpageSubTypeEnum::SHOWROOM->value             => "contents",
                WebpageSubTypeEnum::TERMS_AND_CONDITIONS->value => "contents",
                WebpageSubTypeEnum::PRIVACY->value              => "contents",
                WebpageSubTypeEnum::COOKIES_POLICY->value       => "contents",
                WebpageSubTypeEnum::PRICING->value              => "contents",
                WebpageSubTypeEnum::ARTICLE->value              => "blogs",
                WebpageSubTypeEnum::BLOG->value                 => "blogs",
                WebpageSubTypeEnum::COLLECTION->value           => "collections",
                WebpageSubTypeEnum::STOREFRONT->value           => "pages",
                WebpageSubTypeEnum::BASKET->value               => "pages",
                WebpageSubTypeEnum::CHECKOUT->value             => "pages",
                WebpageSubTypeEnum::LOGIN->value                => "pages",
                WebpageSubTypeEnum::REGISTER->value             => "pages",
                WebpageSubTypeEnum::CALL_BACK->value            => "pages",
                WebpageSubTypeEnum::APPOINTMENT->value          => "pages",
            ];
            $limit = 50000;
            /** @var Webpage $webpage */
            foreach ($webpages as $webpage) {
                if ($count >= $limit) {
                    break;
                }

                $subtype = $webpage->sub_type->value ?? null;

                if (!isset($map[$subtype])) {
                    continue;
                }

                if ($webpage->state != WebpageStateEnum::LIVE) {
                    continue;
                }

                $groupName = $map[$subtype];

                if ($webpage?->liveSnapshot?->published_at) {
                    $groups[$groupName]->add(
                        Url::create($webpage->getCanonicalUrl())
                            ->setLastModificationDate($webpage->liveSnapshot->published_at)
                    );
                } else {
                    $groups[$groupName]->add(Url::create($webpage->getCanonicalUrl()));
                }

                $groupsCount[$groupName]++;
                $count++;
            }
        });

        foreach ($groups as $name => $sitemap) {
            $sitemap->writeToDisk('local', "sitemaps/{$name}_$website->id.xml");
        }

        $indexSitemap = Sitemap::create();
        foreach ($groups as $name => $sitemap) {
            $command?->info("Sitemap $website->slug $name has been saved. (".$groupsCount[$name].")");
            $indexSitemap->add(Url::create($baseUrl."/sitemaps/$name.xml"));
        }
        $command?->info("Sitemaps for website $website->domain done. ($count)");
        $indexSitemap->writeToDisk('local', "sitemaps/sitemap_$website->id.xml");

        return $count;
    }

    public function asJob(Website $website): void
    {
        $this->handle($website);
    }

    public string $commandSignature = 'website_sitemap {website}';

    public function asCommand(Command $command): int
    {
        /** @var Website $website */
        $website = Website::where('slug', $command->argument('website'))->firstOrFail();

        $this->handle($website, $command);

        return 0;
    }
}
