<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 06 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Enums\Web\Crawl;

use App\Enums\EnumHelperTrait;

enum CrawlTabEnum: string
{
    use EnumHelperTrait;

    case INTERNAL         = 'internal';
    case EXTERNAL         = 'external';
    case RESPONSE_CODES   = 'response_codes';
    case URL              = 'url';
    case PAGE_TITLES      = 'page_titles';
    case META_DESCRIPTION = 'meta_description';
    case H1               = 'h1';
    case H2               = 'h2';
    case CONTENT          = 'content';
    case IMAGES           = 'images';
    case CANONICALS       = 'canonicals';
    case DIRECTIVES       = 'directives';
    case HREFLANG         = 'hreflang';
    case PAGINATION       = 'pagination';
    case LINKS            = 'links';
    case STRUCTURED_DATA  = 'structured_data';
    case SOCIAL           = 'social';
    case SITEMAPS         = 'sitemaps';
    case SECURITY         = 'security';
    case VALIDATION       = 'validation';

    public static function labels(): array
    {
        return [
            self::INTERNAL->value         => __('Internal'),
            self::EXTERNAL->value         => __('External'),
            self::RESPONSE_CODES->value   => __('Response codes'),
            self::URL->value              => __('URL'),
            self::PAGE_TITLES->value      => __('Page titles'),
            self::META_DESCRIPTION->value => __('Meta description'),
            self::H1->value               => __('H1'),
            self::H2->value               => __('H2'),
            self::CONTENT->value          => __('Content'),
            self::IMAGES->value           => __('Images'),
            self::CANONICALS->value       => __('Canonicals'),
            self::DIRECTIVES->value       => __('Directives'),
            self::HREFLANG->value         => __('Hreflang'),
            self::PAGINATION->value       => __('Pagination'),
            self::LINKS->value            => __('Links'),
            self::STRUCTURED_DATA->value  => __('Structured data'),
            self::SOCIAL->value           => __('Social tags'),
            self::SITEMAPS->value         => __('Sitemaps'),
            self::SECURITY->value         => __('Security'),
            self::VALIDATION->value       => __('Validation'),
        ];
    }

    /**
     * What the "All" filter of the tab lists: the crawled pages, or the external links or images
     * the pages use.
     */
    public function source(): string
    {
        return match ($this) {
            self::EXTERNAL => 'link',
            self::IMAGES   => 'image',
            default        => 'pages',
        };
    }

    /**
     * The filters of the tab that are not an issue, each listing the rows of the tab's source that
     * match it.
     *
     * @return array<string, string>
     */
    public function stateFilters(): array
    {
        return match ($this) {
            self::INTERNAL       => [
                'indexable'     => __('Indexable'),
                'non_indexable' => __('Non-indexable'),
            ],
            self::RESPONSE_CODES => [
                'success'           => __('Success (2xx)'),
                'redirection'       => __('Redirection (3xx)'),
                'blocked_by_robots' => __('Blocked by robots.txt'),
            ],
            self::EXTERNAL       => [
                'success'      => __('Success (2xx)'),
                'redirection'  => __('Redirection (3xx)'),
                'client_error' => __('Client error (4xx)'),
                'server_error' => __('Server error (5xx)'),
                'no_response'  => __('No response'),
            ],
            self::IMAGES         => [
                'missing_alt'  => __('Missing alt text'),
                'over_size'    => __('Over :size KB', ['size' => CrawlIssueTypeEnum::LARGE_IMAGE_KB]),
                'client_error' => __('Client error (4xx)'),
                'no_response'  => __('No response'),
            ],
            self::H2             => [
                'multiple_h2' => __('Multiple'),
            ],
            self::SITEMAPS       => [
                'in_sitemap'     => __('URLs in sitemap'),
                'not_in_sitemap' => __('URLs not in sitemap'),
            ],
            default              => [],
        };
    }

    /**
     * @return array<int, CrawlIssueTypeEnum>
     */
    public function issueTypes(): array
    {
        return array_values(array_filter(CrawlIssueTypeEnum::cases(), fn (CrawlIssueTypeEnum $type) => $type->tab() === $this));
    }

    /**
     * The columns the tab shows for crawled pages, after the URL.
     *
     * @return array<int, string>
     */
    public function pageColumns(): array
    {
        return match ($this) {
            self::INTERNAL         => ['status_code', 'indexability', 'content_type', 'title', 'word_count', 'link_depth', 'inlinks', 'response_ms', 'bytes'],
            self::RESPONSE_CODES   => ['status_code', 'redirect_to', 'redirect_hops', 'response_ms', 'inlinks'],
            self::URL              => ['status_code', 'url_length', 'inlinks'],
            self::PAGE_TITLES      => ['title', 'title_length', 'title_pixels', 'indexability'],
            self::META_DESCRIPTION => ['meta_description', 'meta_description_length', 'meta_description_pixels', 'indexability'],
            self::H1               => ['h1', 'h1_length', 'h1_count'],
            self::H2               => ['h2_1', 'h2_2', 'h2_count'],
            self::CONTENT          => ['word_count', 'reading_ease', 'indexability', 'inlinks'],
            self::IMAGES           => ['images_without_alt', 'inlinks'],
            self::CANONICALS       => ['canonical', 'indexability'],
            self::DIRECTIVES       => ['robots_meta', 'x_robots_tag', 'indexability'],
            self::HREFLANG         => ['hreflang_count', 'indexability'],
            self::PAGINATION       => ['pagination_next', 'pagination_prev'],
            self::LINKS            => ['link_depth', 'inlinks', 'internal_outlinks'],
            self::STRUCTURED_DATA  => ['status_code', 'indexability'],
            self::SOCIAL           => ['title', 'indexability'],
            self::SITEMAPS         => ['is_in_sitemap', 'sitemap_count', 'indexability'],
            self::SECURITY         => ['status_code', 'indexability'],
            self::VALIDATION       => ['status_code', 'bytes', 'indexability'],
            self::EXTERNAL         => ['status_code', 'inlinks'],
        };
    }
}
