<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Actions\Web\Seo\GenerateSeoContentSuggestions;
use App\Enums\Web\Crawl\CrawlIssueTypeEnum;
use App\Enums\Web\Seo\SeoContentSuggestionStateEnum;
use App\Models\Web\SeoContentSuggestion;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class GetWebpageSeo
{
    use AsAction;
    use WithWebpageSeoData;

    /**
     * The head the public site renders for this webpage, read back so it can be shown and
     * previewed without opening the page.
     *
     * @return array{title: ?string, description: ?string, canonical_url: ?string, robots: string, share_image: array{url: ?string, alt: ?string}, structured_data_types: array<string>}
     */
    public function handle(Webpage $webpage): array
    {
        $website     = $webpage->website;
        $title       = $this->getWebpageSeoTitle($webpage);
        $imageSources = $this->getWebpageShareImageSources($webpage);
        $visibility   = $webpage->searchEngineVisibility();

        return [
            'title'                  => $title,
            'page_title'             => $webpage->title,
            'description'            => $webpage->description,
            'breadcrumb_label'       => $webpage->breadcrumb_label,
            'canonical_url'          => $webpage->canonical_url,
            'url'                    => $webpage->getUrl(),
            'domain'                 => $website->domain,
            'site_name'              => $website->name,
            'index_page'             => $visibility['index_page'],
            'follow_link'            => $visibility['follow_link'],
            'robots'                 => ($visibility['index_page'] ? 'index' : 'noindex').', '.($visibility['follow_link'] ? 'follow' : 'nofollow'),
            'use_title_prefix_suffix' => (bool)Arr::get($webpage->seo_data, 'use_title_prefix_suffix', true),
            'title_prefix'           => data_get($webpage->settings, 'webpage.title_prefix') ?: data_get($website->settings, 'webpage.title_prefix'),
            'title_suffix'           => data_get($webpage->settings, 'webpage.title_suffix') ?: data_get($website->settings, 'webpage.title_suffix'),
            'share_image'            => [
                'url' => Arr::get($imageSources, 'png') ?? Arr::get($imageSources, 'original') ?? Arr::get($imageSources, 'url'),
                'alt' => $this->getWebpageShareImageAlt($webpage),
            ],
            'structured_data'        => $this->structuredData($webpage),
            'structured_data_types'  => $this->structuredDataTypes($webpage),
            'hreflang'               => GetWebpageHreflangAlternates::run($webpage)['alternates'],
            'content_suggestions'    => $this->contentSuggestions($webpage),
        ];
    }

    private function contentSuggestions(Webpage $webpage): array
    {
        return [
            'items'         => SeoContentSuggestion::where('webpage_id', $webpage->id)
                ->where('state', SeoContentSuggestionStateEnum::PENDING)
                ->orderBy('field')
                ->get()
                ->map(fn (SeoContentSuggestion $suggestion) => [
                    'id'            => $suggestion->id,
                    'field'         => $suggestion->field,
                    'current_value' => $suggestion->current_value,
                    'suggestion'    => $suggestion->suggestion,
                    'reason'        => $suggestion->reason,
                    'created_at'    => $suggestion->created_at?->toDateString(),
                    'accept_route'  => ['name' => 'grp.models.seo_content_suggestion.accept', 'parameters' => [$suggestion->id]],
                    'dismiss_route' => ['name' => 'grp.models.seo_content_suggestion.dismiss', 'parameters' => [$suggestion->id]],
                ])
                ->all(),
            'request_route' => ['name' => 'grp.models.webpage.seo_content_suggestions.store', 'parameters' => [$webpage->id]],
            'limits'        => [
                'title'       => GenerateSeoContentSuggestions::make()->titleMaxLength($webpage),
                'description' => CrawlIssueTypeEnum::META_DESCRIPTION_MAX_LENGTH,
            ],
        ];
    }

    private function structuredData(Webpage $webpage): ?array
    {
        $structuredData = Arr::get($webpage->seo_data, 'structured_data');

        if (is_string($structuredData)) {
            $structuredData = json_decode($structuredData, true);
        }

        return is_array($structuredData) && !empty($structuredData) ? $structuredData : null;
    }

    /**
     * @return array<string>
     */
    private function structuredDataTypes(Webpage $webpage): array
    {
        $structuredData = $this->structuredData($webpage);

        if (!$structuredData) {
            return [];
        }

        $entries = array_is_list($structuredData) ? $structuredData : [$structuredData];

        return collect($entries)
            ->map(fn ($entry) => is_array($entry) ? (Arr::get($entry, '@type') ?? Arr::get($entry, 'type')) : null)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
