<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Actions\Helpers\Images\GetPictureSources;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;

trait WithWebpageSeoData
{
    /**
     * The title as the public site renders it in the head, prefix and suffix of the webpage
     * winning over the ones of the website.
     */
    public function getWebpageSeoTitle(Webpage $webpage): ?string
    {
        $title = $webpage->title;

        if (Arr::get($webpage->seo_data, 'use_title_prefix_suffix', true)) {
            $prefix = data_get($webpage->settings, 'webpage.title_prefix') ?: data_get($webpage->website->settings, 'webpage.title_prefix');
            $suffix = data_get($webpage->settings, 'webpage.title_suffix') ?: data_get($webpage->website->settings, 'webpage.title_suffix');
            $title  = collect([$prefix, $title, $suffix])->filter()->implode(' ');
        }

        return $title;
    }

    /**
     * A share image chosen by hand always wins; when there is none the page falls back to the
     * image of the model it shows, so a catalogue page shares the right picture from the moment
     * it is created and keeps following the model when that picture changes.
     *
     * @return array<string, string>
     */
    public function getWebpageShareImageSources(Webpage $webpage): array
    {
        if ($webpage->seo_image_url) {
            return ['url' => $webpage->seo_image_url];
        }

        if ($webpage->seoImage) {
            return $webpage->imageSources(1200, 1200, 'seoImage');
        }

        if ($webpage->type == WebpageTypeEnum::BLOG) {
            $thirdPartyUrl = $this->getBlogThirdPartyImagePreview($webpage);

            return $thirdPartyUrl ? ['url' => $thirdPartyUrl] : [];
        }

        $model = $webpage->model;

        if (!$model || !method_exists($model, 'images')) {
            return [];
        }

        $media = $model->image ?? $model->images->first();

        if (!$media) {
            return [];
        }

        return GetPictureSources::run($media->getImage()->resize(1200, 1200));
    }

    public function getWebpageShareImageAlt(Webpage $webpage): ?string
    {
        return Arr::get($webpage->seo_data, 'image_alt') ?: $this->getWebpageSeoTitle($webpage);
    }

    private function getBlogThirdPartyImagePreview(Webpage $webpage): ?string
    {
        $blogModelHasWebBlock = $webpage->modelHasWebBlocks()
            ->whereHas('webBlock.webBlockType', function ($query) {
                $query->where('code', 'blog');
            })
            ->first();

        return $blogModelHasWebBlock
            ? data_get($blogModelHasWebBlock->webBlock->layout, 'data.fieldValue.third_party_image_preview')
            : null;
    }
}
