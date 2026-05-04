<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 09-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Web\Website;

use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Models\Web\Website;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class SaveWebsiteSitemap implements ShouldBeUnique
{
    use AsAction;

    public int $jobTries = 1;
    public string $jobQueue = 'hydrators-slave';

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

        DB::connection('aiku_no_sticky')->table('products')
            ->select(['webpages.id', 'webpages.url', 'webpages.sub_type', 'snapshots.published_at', 'webpages.canonical_url', 'webpages.model_type', 'webpages.model_id'])
            ->leftJoin('webpages', 'products.webpage_id', '=', 'webpages.id')
            ->leftJoin('snapshots', 'webpages.live_snapshot_id', '=', 'snapshots.id')
            ->where('products.is_for_sale', true)
            ->whereNull('products.deleted_at')
            ->where('products.shop_id', $website->shop_id)
            ->whereIn('products.state', [
                ProductStateEnum::ACTIVE->value,
                ProductStateEnum::DISCONTINUING->value
            ])
            ->where('webpages.state', WebpageStateEnum::LIVE->value)
            ->chunkById($chunkSize, function ($webpages) use (&$count, &$groups, &$groupsCount, $limit) {
                return $this->processWebpagesChunk(
                    $webpages,
                    $count,
                    $groups,
                    $groupsCount,
                    $limit,
                    'products'
                );
            }, 'webpages.id', 'id');

        DB::connection('aiku_no_sticky')->table('product_categories')
            ->select(['webpages.id', 'webpages.url', 'webpages.sub_type', 'snapshots.published_at', 'webpages.canonical_url', 'webpages.model_type', 'webpages.model_id'])
            ->leftJoin('webpages', 'product_categories.webpage_id', '=', 'webpages.id')
            ->leftJoin('snapshots', 'webpages.live_snapshot_id', '=', 'snapshots.id')
            ->whereNull('product_categories.deleted_at')
            ->where('product_categories.shop_id', $website->shop_id)
            ->whereIn('product_categories.state', [
                ProductCategoryStateEnum::ACTIVE->value,
                ProductCategoryStateEnum::DISCONTINUING->value
            ])
            ->where('webpages.state', WebpageStateEnum::LIVE->value)
            ->chunkById($chunkSize, function ($webpages) use (&$count, &$groups, &$groupsCount, $map, $limit) {
                return $this->processWebpagesChunk(
                    $webpages,
                    $count,
                    $groups,
                    $groupsCount,
                    $limit,
                    null,
                    $map
                );
            }, 'webpages.id', 'id');


        DB::connection('aiku_no_sticky')->table('webpages')
            ->select(['webpages.id', 'webpages.url', 'webpages.sub_type', 'snapshots.published_at', 'webpages.canonical_url', 'webpages.model_type', 'webpages.model_id'])
            ->leftJoin('snapshots', 'webpages.live_snapshot_id', '=', 'snapshots.id')
            ->whereNotIn('sub_type', ['product', 'department', 'family', 'sub_department'])
            ->whereNull('webpages.deleted_at')
            ->where('website_id', $website->id)
            ->where('webpages.state', WebpageStateEnum::LIVE->value)
            ->chunkById($chunkSize, function ($webpages) use (&$count, &$groups, &$groupsCount, $map, $limit) {
                return $this->processWebpagesChunk(
                    $webpages,
                    $count,
                    $groups,
                    $groupsCount,
                    $limit,
                    null,
                    $map
                );
            }, 'webpages.id', 'id');


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

    private function processWebpagesChunk(
        iterable $webpages,
        int &$count,
        array &$groups,
        array &$groupsCount,
        int $limit,
        ?string $forcedGroupName = null,
        array $map = []
    ): bool {
        foreach ($webpages as $webpage) {
            if ($count >= $limit) {
                return false;
            }

            $groupName = $forcedGroupName;

            if (!$groupName) {
                $subtype = $webpage->sub_type ?? null;

                if (!isset($map[$subtype])) {
                    continue;
                }

                $groupName = $map[$subtype];
            }

            $this->addSitemapEntry($groups[$groupName], $webpage->canonical_url, $webpage->published_at);

            $groupsCount[$groupName]++;
            $count++;
        }

        return true;
    }

    private function addSitemapEntry(Sitemap $sitemap, string $url, mixed $publishedAt): void
    {
        if ($publishedAt) {
            $sitemap->add(
                Url::create($url)
                    ->setLastModificationDate(Carbon::parse($publishedAt))
            );

            return;
        }

        $sitemap->add(Url::create($url));
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
