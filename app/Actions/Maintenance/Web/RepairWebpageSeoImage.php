<?php

/*
 * Author Louis Perez
 * Created on 17-09-2026-16h-36m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Maintenance\Web;

use App\Actions\Helpers\ClearCacheByWildcard;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithOrganisationSource;
use App\Actions\Web\Webpage\BreakWebpageCache;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Web\Webpage;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Symfony\Component\Console\Helper\ProgressBar;
use Laravel\Nightwatch\Facades\Nightwatch;
use stdClass;

class RepairWebpageSeoImage
{
    use WithActionUpdate;
    use WithOrganisationSource;
    /**
     * @throws \Exception
     */
    public function handle(Webpage $webpage, bool $isBlog = false): void
    {
        if ($webpage->seoImage) {
            return;
        }

        $seoData = $webpage->seo_data;
        data_set($seoData, 'image_alt', $webpage->title, true);

        if ($isBlog) {
            $blogModelHasWebBlock = $webpage->modelHasWebBlocks()
                ->whereHas('webBlock.webBlockType', function ($query) {
                    $query->where('code', 'blog');
                })
                ->first();

            $thirdPartyUrl = $blogModelHasWebBlock
                ? data_get($blogModelHasWebBlock->webBlock->layout, 'data.fieldValue.third_party_image_preview')
                : null;

            if ($thirdPartyUrl) {
                $webpage->updateQuietly([
                    'seo_image_url' => $thirdPartyUrl,
                    'seo_data'      => $seoData
                ]);
            }
        } else {
            $model = $webpage->model;
            if ($model && $model->images->isNotEmpty()) {
                $media = $model->image ?? $model->images->first();

                $webpage->updateQuietly([
                    'seo_image_id'  => $media->id,
                    'seo_data'      => $seoData
                ]);
                $webpage->images()->sync([
                    $media->id => [
                        'group_id'        => $webpage->group_id,
                        'organisation_id' => $webpage->organisation_id,
                        'scope'           => 'seo',
                        'data'            => json_encode(new stdClass())
                    ]
                ]);
            }
        }

        $webpage->refresh();
        BreakWebpageCache::run($webpage, false);
        ClearCacheByWildcard::run("irisData:website:{$webpage->website_id}:*");
    }

    public string $commandSignature = 'repair:webpage_seo_image {shop?}';

    public function asCommand(Command $command): void
    {
        Nightwatch::dontSample();

        $shop = Shop::where('slug', $command->argument('shop'))->first();

        $query = Webpage::when($shop, fn ($q) => $q->where('shop_id', $shop->id))->whereIn('type', [
            WebpageTypeEnum::CATALOGUE,
            WebpageTypeEnum::BLOG
        ]);

        ProgressBar::setFormatDefinition(
            'aiku_eta',
            ' %current%/%max% [%bar%] %percent:3s%% | Elapsed: %elapsed:6s% | ETA: %remaining:6s%'
        );
        $bar = $command->getOutput()->createProgressBar($query->clone()->count());
        $bar->setFormat('aiku_eta');
        $bar->start();

        $query
            ->chunkById(100, function (Collection $models) use ($bar) {
                foreach ($models as $model) {
                    $this->handle($model, $model->type == WebpageTypeEnum::BLOG);
                    $bar->advance();
                }
            });

    }

}
