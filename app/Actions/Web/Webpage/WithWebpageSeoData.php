<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

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

        return [];
    }
}
