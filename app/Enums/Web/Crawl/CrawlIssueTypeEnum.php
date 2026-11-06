<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Enums\Web\Crawl;

use App\Actions\Web\Crawl\AuditWebsite;
use App\Enums\EnumHelperTrait;
use App\Actions\Web\Crawl\EnrichCrawlPages;
use App\Actions\Web\WebVital\UI\IndexWebpagesPageSpeed;
use App\Services\SiteAudit\SerpPixelWidth;

enum CrawlIssueTypeEnum: string
{
    use EnumHelperTrait;

    case HTTP_4XX                    = 'http_4xx';
    case HTTP_5XX                    = 'http_5xx';
    case FETCH_FAILED                = 'fetch_failed';
    case REDIRECT_LOOP               = 'redirect_loop';
    case MISSING_TITLE               = 'missing_title';
    case CANONICAL_TO_BROKEN         = 'canonical_to_broken';
    case REDIRECT_CHAIN              = 'redirect_chain';
    case DUPLICATE_TITLE             = 'duplicate_title';
    case DUPLICATE_META_DESCRIPTION  = 'duplicate_meta_description';
    case MISSING_META_DESCRIPTION    = 'missing_meta_description';
    case MISSING_H1                  = 'missing_h1';
    case MULTIPLE_H1                 = 'multiple_h1';
    case MISSING_CANONICAL           = 'missing_canonical';
    case NOINDEX_IN_SITEMAP          = 'noindex_in_sitemap';
    case SLOW_RESPONSE               = 'slow_response';
    case IMAGES_WITHOUT_ALT          = 'images_without_alt';
    case TITLE_TOO_LONG              = 'title_too_long';
    case TITLE_TOO_SHORT             = 'title_too_short';
    case META_DESCRIPTION_TOO_LONG   = 'meta_description_too_long';
    case META_DESCRIPTION_TOO_SHORT  = 'meta_description_too_short';
    case CANONICAL_TO_OTHER_PAGE     = 'canonical_to_other_page';
    case NOT_IN_SITEMAP              = 'not_in_sitemap';
    case LINKED_REDIRECT             = 'linked_redirect';
    case HREFLANG_TO_BROKEN          = 'hreflang_to_broken';
    case HREFLANG_INVALID_CODE       = 'hreflang_invalid_code';
    case HREFLANG_CONFLICTING_CODE   = 'hreflang_conflicting_code';
    case HREFLANG_MISSING_SELF       = 'hreflang_missing_self';
    case HREFLANG_MISSING_RETURN     = 'hreflang_missing_return';
    case INVALID_STRUCTURED_DATA     = 'invalid_structured_data';
    case NOINDEX_PAGE                = 'noindex_page';
    case NOFOLLOW_PAGE               = 'nofollow_page';
    case CANONICAL_TO_NON_INDEXABLE  = 'canonical_to_non_indexable';
    case SOFT_404                    = 'soft_404';
    case EXACT_DUPLICATE             = 'exact_duplicate';
    case NEAR_DUPLICATE              = 'near_duplicate';
    case LOW_CONTENT                 = 'low_content';
    case LOREM_IPSUM                 = 'lorem_ipsum';
    case DUPLICATE_H1                = 'duplicate_h1';
    case MISSING_H2                  = 'missing_h2';
    case TITLE_SAME_AS_H1            = 'title_same_as_h1';
    case ORPHAN_PAGE                 = 'orphan_page';
    case HIGH_CRAWL_DEPTH            = 'high_crawl_depth';
    case NO_INTERNAL_OUTLINKS        = 'no_internal_outlinks';
    case INTERNAL_NOFOLLOW_LINKS     = 'internal_nofollow_links';
    case EMPTY_ANCHOR_TEXT           = 'empty_anchor_text';
    case NON_DESCRIPTIVE_ANCHOR_TEXT = 'non_descriptive_anchor_text';
    case LOCALHOST_LINKS             = 'localhost_links';
    case UNSAFE_BLANK_LINKS          = 'unsafe_blank_links';
    case BROKEN_EXTERNAL_LINKS       = 'broken_external_links';
    case BROKEN_IMAGES               = 'broken_images';
    case LARGE_IMAGES                = 'large_images';
    case IMAGES_WITHOUT_SIZE         = 'images_without_size';
    case URL_UPPERCASE               = 'url_uppercase';
    case URL_UNDERSCORES             = 'url_underscores';
    case URL_MULTIPLE_SLASHES        = 'url_multiple_slashes';
    case URL_NON_ASCII               = 'url_non_ascii';
    case URL_TOO_LONG                = 'url_too_long';
    case LINKS_WITH_PARAMETERS       = 'links_with_parameters';
    case INTERNAL_HTTP_LINKS         = 'internal_http_links';
    case MIXED_CONTENT               = 'mixed_content';
    case INSECURE_FORMS              = 'insecure_forms';
    case MISSING_SECURITY_HEADERS    = 'missing_security_headers';
    case OPEN_GRAPH_MISSING          = 'open_graph_missing';
    case OPEN_GRAPH_INCOMPLETE       = 'open_graph_incomplete';
    case TWITTER_CARD_MISSING        = 'twitter_card_missing';
    case OG_IMAGE_BROKEN             = 'og_image_broken';
    case MULTIPLE_TITLES             = 'multiple_titles';
    case MULTIPLE_META_DESCRIPTIONS  = 'multiple_meta_descriptions';
    case CONFLICTING_CANONICALS      = 'conflicting_canonicals';
    case HEAD_TAGS_IN_BODY           = 'head_tags_in_body';
    case SITEMAP_NON_200             = 'sitemap_non_200';
    case SITEMAP_CANONICALISED       = 'sitemap_canonicalised';
    case IN_MULTIPLE_SITEMAPS        = 'in_multiple_sitemaps';
    case SITEMAP_TOO_LARGE           = 'sitemap_too_large';
    case PAGINATION_TO_BROKEN        = 'pagination_to_broken';
    case PAGINATION_NOT_LINKED       = 'pagination_not_linked';
    case PAGINATION_LOOP             = 'pagination_loop';
    case TITLE_OVER_PIXELS           = 'title_over_pixels';
    case META_DESCRIPTION_OVER_PIXELS = 'meta_description_over_pixels';
    case H1_TOO_LONG                 = 'h1_too_long';
    case H1_NOT_FIRST_HEADING        = 'h1_not_first_heading';
    case ALT_TEXT_IN_H1              = 'alt_text_in_h1';
    case H2_TOO_LONG                 = 'h2_too_long';
    case DUPLICATE_H2                = 'duplicate_h2';
    case HEADING_LEVEL_SKIPPED       = 'heading_level_skipped';
    case ALT_TEXT_TOO_LONG           = 'alt_text_too_long';
    case READABILITY_DIFFICULT       = 'readability_difficult';
    case READABILITY_VERY_DIFFICULT  = 'readability_very_difficult';
    case CANONICAL_RELATIVE          = 'canonical_relative';
    case CANONICAL_WITH_FRAGMENT     = 'canonical_with_fragment';
    case CANONICAL_UNLINKED          = 'canonical_unlinked';
    case HREFLANG_MISSING_X_DEFAULT  = 'hreflang_missing_x_default';
    case HREFLANG_TO_NON_CANONICAL   = 'hreflang_to_non_canonical';
    case HREFLANG_TO_NOINDEX         = 'hreflang_to_noindex';
    case NOSNIPPET_PAGE              = 'nosnippet_page';
    case NOIMAGEINDEX_PAGE           = 'noimageindex_page';
    case UNAVAILABLE_AFTER_PAGE      = 'unavailable_after_page';
    case META_REFRESH                = 'meta_refresh';
    case BROKEN_BOOKMARK             = 'broken_bookmark';
    case JAVASCRIPT_LINKS            = 'javascript_links';
    case HIGH_INTERNAL_OUTLINKS      = 'high_internal_outlinks';
    case HIGH_EXTERNAL_OUTLINKS      = 'high_external_outlinks';
    case PROTOCOL_RELATIVE_LINKS     = 'protocol_relative_links';
    case LINKS_WITH_TRACKING_PARAMETERS = 'links_with_tracking_parameters';
    case URL_REPETITIVE_PATH         = 'url_repetitive_path';
    case URL_CONTAINS_SPACE          = 'url_contains_space';
    case MISSING_DOCTYPE             = 'missing_doctype';
    case MISSING_HEAD                = 'missing_head';
    case MULTIPLE_HEADS              = 'multiple_heads';
    case MISSING_BODY                = 'missing_body';
    case MULTIPLE_BODIES             = 'multiple_bodies';
    case INVALID_HEAD_ELEMENTS       = 'invalid_head_elements';
    case MISSING_VIEWPORT            = 'missing_viewport';
    case MISSING_CHARSET             = 'missing_charset';
    case HTML_TOO_LARGE              = 'html_too_large';
    case OPEN_GRAPH_MULTIPLE         = 'open_graph_multiple';
    case OG_IMAGE_RELATIVE           = 'og_image_relative';
    case OG_IMAGE_TOO_LARGE          = 'og_image_too_large';
    case DUPLICATE_OG_TITLE          = 'duplicate_og_title';
    case DUPLICATE_OG_DESCRIPTION    = 'duplicate_og_description';
    case STRUCTURED_DATA_WARNINGS    = 'structured_data_warnings';
    case MISSING_PRODUCT_STRUCTURED_DATA = 'missing_product_structured_data';
    case POOR_LCP                    = 'poor_lcp';
    case POOR_INP                    = 'poor_inp';
    case POOR_CLS                    = 'poor_cls';
    case INDEXABLE_WITHOUT_IMPRESSIONS = 'indexable_without_impressions';
    case NON_INDEXABLE_WITH_CLICKS   = 'non_indexable_with_clicks';
    case INDEXABLE_WITHOUT_VISITS    = 'indexable_without_visits';
    case NON_INDEXABLE_WITH_VISITS   = 'non_indexable_with_visits';

    public const int TITLE_MAX_LENGTH            = 60;
    public const int TITLE_MIN_LENGTH            = 20;
    public const int META_DESCRIPTION_MAX_LENGTH = 160;
    public const int META_DESCRIPTION_MIN_LENGTH = 70;
    public const int SLOW_RESPONSE_MS            = 3000;
    public const int LOW_CONTENT_WORDS           = 200;
    public const int HIGH_CRAWL_DEPTH_CLICKS     = 4;
    public const int LARGE_IMAGE_KB              = 200;
    public const int URL_MAX_LENGTH              = 115;
    public const int HEADING_MAX_LENGTH          = 70;
    public const int ALT_TEXT_MAX_LENGTH         = 100;
    public const int READING_EASE_DIFFICULT      = 50;
    public const int READING_EASE_VERY_DIFFICULT = 30;
    public const int MAX_INTERNAL_OUTLINKS       = 1000;
    public const int MAX_EXTERNAL_OUTLINKS       = 100;
    public const int HTML_MAX_MB                 = 15;
    public const int OG_IMAGE_MAX_MB             = 5;

    public function tab(): CrawlTabEnum
    {
        return match ($this) {
            self::HTTP_4XX,
            self::HTTP_5XX,
            self::FETCH_FAILED,
            self::REDIRECT_LOOP,
            self::REDIRECT_CHAIN,
            self::LINKED_REDIRECT,
            self::SLOW_RESPONSE => CrawlTabEnum::RESPONSE_CODES,

            self::MISSING_TITLE,
            self::DUPLICATE_TITLE,
            self::TITLE_TOO_LONG,
            self::TITLE_TOO_SHORT,
            self::TITLE_SAME_AS_H1,
            self::MULTIPLE_TITLES => CrawlTabEnum::PAGE_TITLES,

            self::MISSING_META_DESCRIPTION,
            self::DUPLICATE_META_DESCRIPTION,
            self::META_DESCRIPTION_TOO_LONG,
            self::META_DESCRIPTION_TOO_SHORT,
            self::MULTIPLE_META_DESCRIPTIONS => CrawlTabEnum::META_DESCRIPTION,

            self::MISSING_H1,
            self::MULTIPLE_H1,
            self::DUPLICATE_H1 => CrawlTabEnum::H1,

            self::MISSING_H2 => CrawlTabEnum::H2,

            self::SOFT_404,
            self::EXACT_DUPLICATE,
            self::NEAR_DUPLICATE,
            self::LOW_CONTENT,
            self::LOREM_IPSUM => CrawlTabEnum::CONTENT,

            self::IMAGES_WITHOUT_ALT,
            self::BROKEN_IMAGES,
            self::LARGE_IMAGES,
            self::IMAGES_WITHOUT_SIZE => CrawlTabEnum::IMAGES,

            self::MISSING_CANONICAL,
            self::CANONICAL_TO_BROKEN,
            self::CANONICAL_TO_OTHER_PAGE,
            self::CANONICAL_TO_NON_INDEXABLE,
            self::CONFLICTING_CANONICALS => CrawlTabEnum::CANONICALS,

            self::NOINDEX_PAGE,
            self::NOFOLLOW_PAGE,
            self::HEAD_TAGS_IN_BODY => CrawlTabEnum::DIRECTIVES,

            self::HREFLANG_TO_BROKEN,
            self::HREFLANG_INVALID_CODE,
            self::HREFLANG_CONFLICTING_CODE,
            self::HREFLANG_MISSING_SELF,
            self::HREFLANG_MISSING_RETURN => CrawlTabEnum::HREFLANG,

            self::PAGINATION_TO_BROKEN,
            self::PAGINATION_NOT_LINKED,
            self::PAGINATION_LOOP => CrawlTabEnum::PAGINATION,

            self::ORPHAN_PAGE,
            self::HIGH_CRAWL_DEPTH,
            self::NO_INTERNAL_OUTLINKS,
            self::INTERNAL_NOFOLLOW_LINKS,
            self::EMPTY_ANCHOR_TEXT,
            self::NON_DESCRIPTIVE_ANCHOR_TEXT,
            self::LOCALHOST_LINKS,
            self::LINKS_WITH_PARAMETERS => CrawlTabEnum::LINKS,

            self::BROKEN_EXTERNAL_LINKS,
            self::UNSAFE_BLANK_LINKS => CrawlTabEnum::EXTERNAL,

            self::URL_UPPERCASE,
            self::URL_UNDERSCORES,
            self::URL_MULTIPLE_SLASHES,
            self::URL_NON_ASCII,
            self::URL_TOO_LONG => CrawlTabEnum::URL,

            self::INTERNAL_HTTP_LINKS,
            self::MIXED_CONTENT,
            self::INSECURE_FORMS,
            self::MISSING_SECURITY_HEADERS => CrawlTabEnum::SECURITY,

            self::INVALID_STRUCTURED_DATA => CrawlTabEnum::STRUCTURED_DATA,

            self::OPEN_GRAPH_MISSING,
            self::OPEN_GRAPH_INCOMPLETE,
            self::TWITTER_CARD_MISSING,
            self::OG_IMAGE_BROKEN => CrawlTabEnum::SOCIAL,

            self::NOINDEX_IN_SITEMAP,
            self::NOT_IN_SITEMAP,
            self::SITEMAP_NON_200,
            self::SITEMAP_CANONICALISED,
            self::IN_MULTIPLE_SITEMAPS,
            self::SITEMAP_TOO_LARGE => CrawlTabEnum::SITEMAPS,

            self::INDEXABLE_WITHOUT_VISITS,
            self::NON_INDEXABLE_WITH_VISITS => CrawlTabEnum::VISITS,

            self::STRUCTURED_DATA_WARNINGS,
            self::MISSING_PRODUCT_STRUCTURED_DATA => CrawlTabEnum::STRUCTURED_DATA,

            self::POOR_LCP,
            self::POOR_INP,
            self::POOR_CLS => CrawlTabEnum::PAGE_SPEED,

            self::INDEXABLE_WITHOUT_IMPRESSIONS,
            self::NON_INDEXABLE_WITH_CLICKS => CrawlTabEnum::SEARCH_CONSOLE,

            self::TITLE_OVER_PIXELS => CrawlTabEnum::PAGE_TITLES,

            self::META_DESCRIPTION_OVER_PIXELS => CrawlTabEnum::META_DESCRIPTION,

            self::H1_TOO_LONG,
            self::H1_NOT_FIRST_HEADING,
            self::ALT_TEXT_IN_H1 => CrawlTabEnum::H1,

            self::H2_TOO_LONG,
            self::DUPLICATE_H2,
            self::HEADING_LEVEL_SKIPPED => CrawlTabEnum::H2,

            self::ALT_TEXT_TOO_LONG => CrawlTabEnum::IMAGES,

            self::READABILITY_DIFFICULT,
            self::READABILITY_VERY_DIFFICULT => CrawlTabEnum::CONTENT,

            self::CANONICAL_RELATIVE,
            self::CANONICAL_WITH_FRAGMENT,
            self::CANONICAL_UNLINKED => CrawlTabEnum::CANONICALS,

            self::HREFLANG_MISSING_X_DEFAULT,
            self::HREFLANG_TO_NON_CANONICAL,
            self::HREFLANG_TO_NOINDEX => CrawlTabEnum::HREFLANG,

            self::NOSNIPPET_PAGE,
            self::NOIMAGEINDEX_PAGE,
            self::UNAVAILABLE_AFTER_PAGE => CrawlTabEnum::DIRECTIVES,

            self::META_REFRESH => CrawlTabEnum::RESPONSE_CODES,

            self::BROKEN_BOOKMARK,
            self::JAVASCRIPT_LINKS,
            self::HIGH_INTERNAL_OUTLINKS => CrawlTabEnum::LINKS,

            self::HIGH_EXTERNAL_OUTLINKS => CrawlTabEnum::EXTERNAL,

            self::PROTOCOL_RELATIVE_LINKS => CrawlTabEnum::SECURITY,

            self::LINKS_WITH_TRACKING_PARAMETERS,
            self::URL_REPETITIVE_PATH,
            self::URL_CONTAINS_SPACE => CrawlTabEnum::URL,

            self::MISSING_DOCTYPE,
            self::MISSING_HEAD,
            self::MULTIPLE_HEADS,
            self::MISSING_BODY,
            self::MULTIPLE_BODIES,
            self::INVALID_HEAD_ELEMENTS,
            self::MISSING_VIEWPORT,
            self::MISSING_CHARSET,
            self::HTML_TOO_LARGE => CrawlTabEnum::VALIDATION,

            self::OPEN_GRAPH_MULTIPLE,
            self::OG_IMAGE_RELATIVE,
            self::OG_IMAGE_TOO_LARGE,
            self::DUPLICATE_OG_TITLE,
            self::DUPLICATE_OG_DESCRIPTION => CrawlTabEnum::SOCIAL,
        };
    }

    public function severity(): CrawlIssueSeverityEnum
    {
        return match ($this) {
            self::HTTP_4XX,
            self::HTTP_5XX,
            self::FETCH_FAILED,
            self::REDIRECT_LOOP,
            self::MISSING_TITLE,
            self::CANONICAL_TO_BROKEN,
            self::HREFLANG_TO_BROKEN => CrawlIssueSeverityEnum::ERROR,

            self::REDIRECT_CHAIN,
            self::DUPLICATE_TITLE,
            self::DUPLICATE_META_DESCRIPTION,
            self::MISSING_META_DESCRIPTION,
            self::MISSING_H1,
            self::MULTIPLE_H1,
            self::MISSING_CANONICAL,
            self::NOINDEX_IN_SITEMAP,
            self::SLOW_RESPONSE,
            self::IMAGES_WITHOUT_ALT,
            self::HREFLANG_INVALID_CODE,
            self::HREFLANG_CONFLICTING_CODE,
            self::HREFLANG_MISSING_SELF,
            self::HREFLANG_MISSING_RETURN,
            self::NOFOLLOW_PAGE,
            self::CANONICAL_TO_NON_INDEXABLE,
            self::SOFT_404,
            self::EXACT_DUPLICATE,
            self::LOREM_IPSUM,
            self::ORPHAN_PAGE,
            self::NO_INTERNAL_OUTLINKS,
            self::LOCALHOST_LINKS,
            self::BROKEN_EXTERNAL_LINKS,
            self::BROKEN_IMAGES,
            self::URL_MULTIPLE_SLASHES,
            self::INTERNAL_HTTP_LINKS,
            self::MIXED_CONTENT,
            self::INSECURE_FORMS,
            self::OPEN_GRAPH_MISSING,
            self::OG_IMAGE_BROKEN,
            self::MULTIPLE_TITLES,
            self::MULTIPLE_META_DESCRIPTIONS,
            self::CONFLICTING_CANONICALS,
            self::HEAD_TAGS_IN_BODY,
            self::SITEMAP_NON_200,
            self::SITEMAP_TOO_LARGE,
            self::PAGINATION_TO_BROKEN,
            self::PAGINATION_LOOP,
            self::INVALID_STRUCTURED_DATA,
            self::CANONICAL_WITH_FRAGMENT,
            self::HREFLANG_TO_NON_CANONICAL,
            self::HREFLANG_TO_NOINDEX,
            self::NOSNIPPET_PAGE,
            self::META_REFRESH,
            self::LINKS_WITH_TRACKING_PARAMETERS,
            self::URL_CONTAINS_SPACE,
            self::MISSING_DOCTYPE,
            self::MISSING_HEAD,
            self::MULTIPLE_HEADS,
            self::MISSING_BODY,
            self::MULTIPLE_BODIES,
            self::INVALID_HEAD_ELEMENTS,
            self::MISSING_VIEWPORT,
            self::HTML_TOO_LARGE,
            self::OG_IMAGE_RELATIVE,
            self::MISSING_PRODUCT_STRUCTURED_DATA,
            self::POOR_LCP,
            self::POOR_INP,
            self::POOR_CLS,
            self::NON_INDEXABLE_WITH_CLICKS => CrawlIssueSeverityEnum::WARNING,

            self::TITLE_TOO_LONG,
            self::TITLE_TOO_SHORT,
            self::META_DESCRIPTION_TOO_LONG,
            self::META_DESCRIPTION_TOO_SHORT,
            self::CANONICAL_TO_OTHER_PAGE,
            self::NOT_IN_SITEMAP,
            self::NOINDEX_PAGE,
            self::NEAR_DUPLICATE,
            self::LOW_CONTENT,
            self::DUPLICATE_H1,
            self::MISSING_H2,
            self::TITLE_SAME_AS_H1,
            self::HIGH_CRAWL_DEPTH,
            self::INTERNAL_NOFOLLOW_LINKS,
            self::EMPTY_ANCHOR_TEXT,
            self::NON_DESCRIPTIVE_ANCHOR_TEXT,
            self::UNSAFE_BLANK_LINKS,
            self::LARGE_IMAGES,
            self::IMAGES_WITHOUT_SIZE,
            self::URL_UPPERCASE,
            self::URL_UNDERSCORES,
            self::URL_NON_ASCII,
            self::URL_TOO_LONG,
            self::LINKS_WITH_PARAMETERS,
            self::MISSING_SECURITY_HEADERS,
            self::OPEN_GRAPH_INCOMPLETE,
            self::TWITTER_CARD_MISSING,
            self::SITEMAP_CANONICALISED,
            self::IN_MULTIPLE_SITEMAPS,
            self::PAGINATION_NOT_LINKED,
            self::LINKED_REDIRECT,
            self::TITLE_OVER_PIXELS,
            self::META_DESCRIPTION_OVER_PIXELS,
            self::H1_TOO_LONG,
            self::H1_NOT_FIRST_HEADING,
            self::ALT_TEXT_IN_H1,
            self::H2_TOO_LONG,
            self::DUPLICATE_H2,
            self::HEADING_LEVEL_SKIPPED,
            self::ALT_TEXT_TOO_LONG,
            self::READABILITY_DIFFICULT,
            self::READABILITY_VERY_DIFFICULT,
            self::CANONICAL_RELATIVE,
            self::CANONICAL_UNLINKED,
            self::HREFLANG_MISSING_X_DEFAULT,
            self::NOIMAGEINDEX_PAGE,
            self::UNAVAILABLE_AFTER_PAGE,
            self::BROKEN_BOOKMARK,
            self::JAVASCRIPT_LINKS,
            self::HIGH_INTERNAL_OUTLINKS,
            self::HIGH_EXTERNAL_OUTLINKS,
            self::PROTOCOL_RELATIVE_LINKS,
            self::URL_REPETITIVE_PATH,
            self::MISSING_CHARSET,
            self::OPEN_GRAPH_MULTIPLE,
            self::OG_IMAGE_TOO_LARGE,
            self::DUPLICATE_OG_TITLE,
            self::DUPLICATE_OG_DESCRIPTION,
            self::STRUCTURED_DATA_WARNINGS,
            self::INDEXABLE_WITHOUT_IMPRESSIONS,
            self::INDEXABLE_WITHOUT_VISITS,
            self::NON_INDEXABLE_WITH_VISITS => CrawlIssueSeverityEnum::NOTICE,
        };
    }

    public static function labels(): array
    {
        return [
            self::HTTP_4XX->value                    => __('Page returns 4xx'),
            self::HTTP_5XX->value                    => __('Page returns 5xx'),
            self::FETCH_FAILED->value                => __('Page could not be fetched'),
            self::REDIRECT_LOOP->value               => __('Redirect loop'),
            self::MISSING_TITLE->value               => __('Missing title'),
            self::CANONICAL_TO_BROKEN->value         => __('Canonical points to a broken or redirecting URL'),
            self::REDIRECT_CHAIN->value              => __('Redirect chain'),
            self::DUPLICATE_TITLE->value             => __('Duplicate title'),
            self::DUPLICATE_META_DESCRIPTION->value  => __('Duplicate meta description'),
            self::MISSING_META_DESCRIPTION->value    => __('Missing meta description'),
            self::MISSING_H1->value                  => __('Missing h1'),
            self::MULTIPLE_H1->value                 => __('More than one h1'),
            self::MISSING_CANONICAL->value           => __('Missing canonical'),
            self::NOINDEX_IN_SITEMAP->value          => __('Noindex page listed in the sitemap'),
            self::SLOW_RESPONSE->value               => __('Slow response'),
            self::IMAGES_WITHOUT_ALT->value          => __('Images without alt text'),
            self::TITLE_TOO_LONG->value              => __('Title too long'),
            self::TITLE_TOO_SHORT->value             => __('Title too short'),
            self::META_DESCRIPTION_TOO_LONG->value   => __('Meta description too long'),
            self::META_DESCRIPTION_TOO_SHORT->value  => __('Meta description too short'),
            self::CANONICAL_TO_OTHER_PAGE->value     => __('Canonical points to another page'),
            self::NOT_IN_SITEMAP->value              => __('Indexable page missing from the sitemap'),
            self::LINKED_REDIRECT->value             => __('Internal links point to a redirect'),
            self::HREFLANG_TO_BROKEN->value          => __('Hreflang points to a broken or redirecting URL'),
            self::HREFLANG_INVALID_CODE->value       => __('Invalid hreflang code'),
            self::HREFLANG_CONFLICTING_CODE->value   => __('Hreflang code used for more than one URL'),
            self::HREFLANG_MISSING_SELF->value       => __('Hreflang does not list the page itself'),
            self::HREFLANG_MISSING_RETURN->value     => __('Hreflang alternate does not link back'),
            self::INVALID_STRUCTURED_DATA->value     => __('Structured data not eligible for rich results'),
            self::NOINDEX_PAGE->value                => __('Page tells search engines not to index it'),
            self::NOFOLLOW_PAGE->value               => __('Page tells search engines not to follow its links'),
            self::CANONICAL_TO_NON_INDEXABLE->value  => __('Canonical points to a page that cannot be indexed'),
            self::SOFT_404->value                    => __('Page looks like an error page but answers 200'),
            self::EXACT_DUPLICATE->value             => __('Same content as another page'),
            self::NEAR_DUPLICATE->value              => __('Content nearly the same as another page'),
            self::LOW_CONTENT->value                 => __('Low content'),
            self::LOREM_IPSUM->value                 => __('Placeholder text (lorem ipsum)'),
            self::DUPLICATE_H1->value                => __('Duplicate h1'),
            self::MISSING_H2->value                  => __('Missing h2'),
            self::TITLE_SAME_AS_H1->value            => __('Title same as h1'),
            self::ORPHAN_PAGE->value                 => __('Page in the sitemap that no link reaches'),
            self::HIGH_CRAWL_DEPTH->value            => __('Many clicks from the home page'),
            self::NO_INTERNAL_OUTLINKS->value        => __('Page has no links to other pages of the website'),
            self::INTERNAL_NOFOLLOW_LINKS->value     => __('Nofollow links to pages of the website'),
            self::EMPTY_ANCHOR_TEXT->value           => __('Links without anchor text'),
            self::NON_DESCRIPTIVE_ANCHOR_TEXT->value => __('Links with non-descriptive anchor text'),
            self::LOCALHOST_LINKS->value             => __('Links to localhost or a test domain'),
            self::UNSAFE_BLANK_LINKS->value          => __('External links opening a new tab without rel="noopener"'),
            self::BROKEN_EXTERNAL_LINKS->value       => __('Broken links to other websites'),
            self::BROKEN_IMAGES->value               => __('Broken images'),
            self::LARGE_IMAGES->value                => __('Images over :kb KB', ['kb' => self::LARGE_IMAGE_KB]),
            self::IMAGES_WITHOUT_SIZE->value         => __('Images without width and height'),
            self::URL_UPPERCASE->value               => __('URL has uppercase letters'),
            self::URL_UNDERSCORES->value             => __('URL has underscores'),
            self::URL_MULTIPLE_SLASHES->value        => __('URL has double slashes'),
            self::URL_NON_ASCII->value               => __('URL has non-ASCII characters'),
            self::URL_TOO_LONG->value                => __('URL longer than :length characters', ['length' => self::URL_MAX_LENGTH]),
            self::LINKS_WITH_PARAMETERS->value       => __('Links to URLs with parameters'),
            self::INTERNAL_HTTP_LINKS->value         => __('Links to the website over HTTP'),
            self::MIXED_CONTENT->value               => __('HTTPS page loads files over HTTP'),
            self::INSECURE_FORMS->value              => __('Form sends data over HTTP'),
            self::MISSING_SECURITY_HEADERS->value    => __('Missing security headers'),
            self::OPEN_GRAPH_MISSING->value          => __('Missing Open Graph tags'),
            self::OPEN_GRAPH_INCOMPLETE->value       => __('Incomplete Open Graph tags'),
            self::TWITTER_CARD_MISSING->value        => __('Missing X (Twitter) card'),
            self::OG_IMAGE_BROKEN->value             => __('Open Graph image does not load'),
            self::MULTIPLE_TITLES->value             => __('More than one title tag'),
            self::MULTIPLE_META_DESCRIPTIONS->value  => __('More than one meta description'),
            self::CONFLICTING_CANONICALS->value      => __('Canonical tags pointing to different URLs'),
            self::HEAD_TAGS_IN_BODY->value           => __('Head tags inside the body'),
            self::SITEMAP_NON_200->value             => __('Sitemap lists a URL that does not answer 200'),
            self::SITEMAP_CANONICALISED->value       => __('Sitemap lists a URL whose canonical is another page'),
            self::IN_MULTIPLE_SITEMAPS->value        => __('URL listed in more than one sitemap'),
            self::SITEMAP_TOO_LARGE->value           => __('Sitemap over the size limits'),
            self::PAGINATION_TO_BROKEN->value        => __('Pagination points to a broken page'),
            self::PAGINATION_NOT_LINKED->value       => __('Pagination URL not linked on the page'),
            self::PAGINATION_LOOP->value             => __('Pagination points to the page itself'),
            self::TITLE_OVER_PIXELS->value           => __('Title wider than results show'),
            self::META_DESCRIPTION_OVER_PIXELS->value => __('Meta description wider than results show'),
            self::H1_TOO_LONG->value                 => __('H1 too long'),
            self::H1_NOT_FIRST_HEADING->value        => __('H1 is not the first heading'),
            self::ALT_TEXT_IN_H1->value              => __('H1 made of an image'),
            self::H2_TOO_LONG->value                 => __('H2 too long'),
            self::DUPLICATE_H2->value                => __('Duplicate h2'),
            self::HEADING_LEVEL_SKIPPED->value       => __('Heading levels skipped'),
            self::ALT_TEXT_TOO_LONG->value           => __('Alt text too long'),
            self::READABILITY_DIFFICULT->value       => __('Text difficult to read'),
            self::READABILITY_VERY_DIFFICULT->value  => __('Text very difficult to read'),
            self::CANONICAL_RELATIVE->value          => __('Canonical is a relative URL'),
            self::CANONICAL_WITH_FRAGMENT->value     => __('Canonical contains a fragment'),
            self::CANONICAL_UNLINKED->value          => __('Canonical URL not linked'),
            self::HREFLANG_MISSING_X_DEFAULT->value  => __('Hreflang without x-default'),
            self::HREFLANG_TO_NON_CANONICAL->value   => __('Hreflang points to a non-canonical URL'),
            self::HREFLANG_TO_NOINDEX->value         => __('Hreflang points to a noindex page'),
            self::NOSNIPPET_PAGE->value              => __('Page hides its snippet in results'),
            self::NOIMAGEINDEX_PAGE->value           => __('Images of the page kept out of image search'),
            self::UNAVAILABLE_AFTER_PAGE->value      => __('Page set to leave results on a date'),
            self::META_REFRESH->value                => __('Page redirects with a refresh'),
            self::BROKEN_BOOKMARK->value             => __('Links to a missing part of the page'),
            self::JAVASCRIPT_LINKS->value            => __('Links that only work with JavaScript'),
            self::HIGH_INTERNAL_OUTLINKS->value      => __('Too many internal links'),
            self::HIGH_EXTERNAL_OUTLINKS->value      => __('Too many external links'),
            self::PROTOCOL_RELATIVE_LINKS->value     => __('Protocol-relative links'),
            self::LINKS_WITH_TRACKING_PARAMETERS->value => __('Internal links with tracking parameters'),
            self::URL_REPETITIVE_PATH->value         => __('Repeated folder in the URL'),
            self::URL_CONTAINS_SPACE->value          => __('Space in the URL'),
            self::MISSING_DOCTYPE->value             => __('Missing doctype'),
            self::MISSING_HEAD->value                => __('Missing head tag'),
            self::MULTIPLE_HEADS->value              => __('More than one head tag'),
            self::MISSING_BODY->value                => __('Missing body tag'),
            self::MULTIPLE_BODIES->value             => __('More than one body tag'),
            self::INVALID_HEAD_ELEMENTS->value       => __('Invalid elements in the head'),
            self::MISSING_VIEWPORT->value            => __('Missing viewport'),
            self::MISSING_CHARSET->value             => __('Missing character set'),
            self::HTML_TOO_LARGE->value              => __('HTML over :mb MB', ['mb' => self::HTML_MAX_MB]),
            self::OPEN_GRAPH_MULTIPLE->value         => __('Social tags written more than once'),
            self::OG_IMAGE_RELATIVE->value           => __('Open Graph image is a relative URL'),
            self::OG_IMAGE_TOO_LARGE->value          => __('Open Graph image over :mb MB', ['mb' => self::OG_IMAGE_MAX_MB]),
            self::DUPLICATE_OG_TITLE->value          => __('Duplicate Open Graph title'),
            self::DUPLICATE_OG_DESCRIPTION->value    => __('Duplicate Open Graph description'),
            self::STRUCTURED_DATA_WARNINGS->value    => __('Structured data missing recommended fields'),
            self::MISSING_PRODUCT_STRUCTURED_DATA->value => __('Product page without Product structured data'),
            self::POOR_LCP->value                    => __('Slow to show its main content (LCP)'),
            self::POOR_INP->value                    => __('Slow to respond to clicks (INP)'),
            self::POOR_CLS->value                    => __('Layout jumps while loading (CLS)'),
            self::INDEXABLE_WITHOUT_IMPRESSIONS->value => __('Indexable page with no Google impressions'),
            self::NON_INDEXABLE_WITH_CLICKS->value   => __('Non-indexable page still getting search clicks'),
            self::INDEXABLE_WITHOUT_VISITS->value    => __('Indexable page nobody visited'),
            self::NON_INDEXABLE_WITH_VISITS->value   => __('Non-indexable page that visitors use'),
        ];
    }

    public static function descriptions(): array
    {
        return [
            self::HTTP_4XX->value                    => __('The page answers with a 4xx status. Visitors and Google that follow links to it reach a dead end.'),
            self::HTTP_5XX->value                    => __('The server failed to render the page. Google drops pages that keep failing.'),
            self::FETCH_FAILED->value                => __('The request timed out or the connection failed, so nothing could be checked.'),
            self::REDIRECT_LOOP->value               => __('The redirects lead back to a URL already in the chain, so the page never loads.'),
            self::MISSING_TITLE->value               => __('The page has no title tag. Google writes its own, usually a poor one.'),
            self::CANONICAL_TO_BROKEN->value         => __('The canonical URL does not answer with 200, so Google may ignore it or drop the page.'),
            self::REDIRECT_CHAIN->value              => __('The URL redirects more than once before reaching a page. Each hop slows the page and wastes crawl budget.'),
            self::DUPLICATE_TITLE->value             => __('Another indexable page has the same title, so Google cannot tell them apart in results.'),
            self::DUPLICATE_META_DESCRIPTION->value  => __('Another indexable page has the same meta description.'),
            self::MISSING_META_DESCRIPTION->value    => __('The page has no meta description. Google picks a snippet from the page text instead.'),
            self::MISSING_H1->value                  => __('The page has no h1 heading.'),
            self::MULTIPLE_H1->value                 => __('The page has more than one h1 heading.'),
            self::MISSING_CANONICAL->value           => __('The page does not say which URL is the main one, so duplicates with other URLs are possible.'),
            self::NOINDEX_IN_SITEMAP->value          => __('The sitemap asks Google to index a page that tells Google not to index it.'),
            self::SLOW_RESPONSE->value               => __('The server took more than :seconds seconds to answer.', ['seconds' => self::SLOW_RESPONSE_MS / 1000]),
            self::IMAGES_WITHOUT_ALT->value          => __('Some images have no alt attribute, so screen readers and image search cannot describe them.'),
            self::TITLE_TOO_LONG->value              => __('The title is longer than :length characters and is likely cut off in results.', ['length' => self::TITLE_MAX_LENGTH]),
            self::TITLE_TOO_SHORT->value             => __('The title is shorter than :length characters.', ['length' => self::TITLE_MIN_LENGTH]),
            self::META_DESCRIPTION_TOO_LONG->value   => __('The meta description is longer than :length characters and is likely cut off.', ['length' => self::META_DESCRIPTION_MAX_LENGTH]),
            self::META_DESCRIPTION_TOO_SHORT->value  => __('The meta description is shorter than :length characters.', ['length' => self::META_DESCRIPTION_MIN_LENGTH]),
            self::CANONICAL_TO_OTHER_PAGE->value     => __('The page names another URL as canonical, so Google indexes that URL instead. Fine for variants, wrong for unique pages.'),
            self::NOT_IN_SITEMAP->value              => __('Google can index the page, but the sitemap does not list it.'),
            self::LINKED_REDIRECT->value             => __('Links inside the website point to a URL that redirects. Linking to the final URL saves a hop.'),
            self::HREFLANG_TO_BROKEN->value          => __('A language version listed in hreflang does not answer with 200. Google ignores the whole set of alternates when one of them is broken.'),
            self::HREFLANG_INVALID_CODE->value       => __('The hreflang value is not a language code (ISO 639-1), a language and region (for example en-GB) or x-default, so Google ignores it.'),
            self::HREFLANG_CONFLICTING_CODE->value   => __('The same hreflang code points to different URLs, so Google cannot tell which one to show.'),
            self::HREFLANG_MISSING_SELF->value       => __('The page lists other language versions but not itself. Every version must list all versions, itself included.'),
            self::HREFLANG_MISSING_RETURN->value     => __('A language version listed here does not list this page back, so Google ignores the pair. Checked against the latest audit of the other website.'),
            self::INVALID_STRUCTURED_DATA->value     => __('Checked like the Google Rich Results Test: the rendered page has no rich result item, an item misses a required field, or a JSON-LD block cannot be parsed.'),
            self::NOINDEX_PAGE->value                => __('The page has a noindex robots meta tag or X-Robots-Tag header, so Google leaves it out of results. Fine for account or checkout pages, a problem for pages that should rank.'),
            self::NOFOLLOW_PAGE->value               => __('The robots meta tag or X-Robots-Tag header says nofollow, so Google does not follow any link on the page to the pages behind it.'),
            self::CANONICAL_TO_NON_INDEXABLE->value  => __('The canonical URL is noindex or names yet another URL as its canonical, so Google gets mixed signals and may index neither.'),
            self::SOFT_404->value                    => __('The title or h1 says the page was not found, yet the server answers 200. Google treats it as a soft 404 and wastes crawl budget on it.'),
            self::EXACT_DUPLICATE->value             => __('The text of the page is identical to another indexable page, so Google picks one and ignores the rest.'),
            self::NEAR_DUPLICATE->value              => __('The text of the page is about 95% the same as another indexable page. Make each page say something of its own or point the copies to one canonical.'),
            self::LOW_CONTENT->value                 => __('The page has fewer than :words words of its own content, which may be too little for Google to understand what it is about.', ['words' => self::LOW_CONTENT_WORDS]),
            self::LOREM_IPSUM->value                 => __('The page still contains lorem ipsum placeholder text.'),
            self::DUPLICATE_H1->value                => __('Another indexable page has the same h1 heading.'),
            self::MISSING_H2->value                  => __('The page has no h2 heading to structure its content.'),
            self::TITLE_SAME_AS_H1->value            => __('The title and the h1 are identical, a missed chance to target more search terms.'),
            self::ORPHAN_PAGE->value                 => __('The sitemap lists the page, but following links from the home page never reaches it. Google crawls such pages rarely and gives them little weight.'),
            self::HIGH_CRAWL_DEPTH->value            => __('The page is :clicks or more clicks away from the home page. Pages buried deep are crawled less often and seen as less important.', ['clicks' => self::HIGH_CRAWL_DEPTH_CLICKS]),
            self::NO_INTERNAL_OUTLINKS->value        => __('The page is a dead end for visitors and Google: it links to no other page of the website.'),
            self::INTERNAL_NOFOLLOW_LINKS->value     => __('The page links to other pages of the website with rel="nofollow", which stops Google from following them.'),
            self::EMPTY_ANCHOR_TEXT->value           => __('Some links have no text, no image alt and no aria-label, so Google and screen readers cannot tell where they lead.'),
            self::NON_DESCRIPTIVE_ANCHOR_TEXT->value => __('Some links say only "click here", "read more" or similar, which tells nothing about the page behind them.'),
            self::LOCALHOST_LINKS->value             => __('The page links to localhost or a .test or .local address, which only works on a developer machine.'),
            self::UNSAFE_BLANK_LINKS->value          => __('Links to other websites open in a new tab without rel="noopener" or "noreferrer", which lets the other site control this tab in older browsers.'),
            self::BROKEN_EXTERNAL_LINKS->value       => __('The page links to URLs on other websites that answer with an error or do not answer. Sites that refuse bots (401, 403, 405, 429) are not counted.'),
            self::BROKEN_IMAGES->value               => __('Some images on the page answer with an error or do not load.'),
            self::LARGE_IMAGES->value                => __('Some images on the page are larger than :kb KB, which slows the page down, especially on mobile.', ['kb' => self::LARGE_IMAGE_KB]),
            self::IMAGES_WITHOUT_SIZE->value         => __('Some images have no width and height attributes, so the page jumps while they load (layout shift, a Core Web Vitals metric).'),
            self::URL_UPPERCASE->value               => __('URLs are case sensitive, so uppercase letters invite duplicates and broken links typed in lowercase.'),
            self::URL_UNDERSCORES->value             => __('Google reads hyphens as word separators but not underscores.'),
            self::URL_MULTIPLE_SLASHES->value        => __('The path has // in it, usually a broken link building a duplicate URL.'),
            self::URL_NON_ASCII->value               => __('The URL has characters outside ASCII, which must be encoded and often break when copied or shared.'),
            self::URL_TOO_LONG->value                => __('Long URLs are cut off in results and are harder to share.'),
            self::LINKS_WITH_PARAMETERS->value       => __('The page links to pages of the website with ?parameters, which create duplicate URLs to crawl. The audit does not follow them.'),
            self::INTERNAL_HTTP_LINKS->value         => __('The page links to pages of the website with http:// instead of https://, adding a redirect and a moment without encryption.'),
            self::MIXED_CONTENT->value               => __('Images, scripts or styles load over http:// on an https:// page. Browsers block them or mark the page as not secure.'),
            self::INSECURE_FORMS->value              => __('A form on the page submits to an http:// address, so what visitors type travels without encryption.'),
            self::MISSING_SECURITY_HEADERS->value    => __('The response lacks headers that protect visitors: HSTS, Content-Security-Policy, X-Content-Type-Options, X-Frame-Options or Referrer-Policy.'),
            self::OPEN_GRAPH_MISSING->value          => __('The page has no og: tags, so Facebook, WhatsApp, LinkedIn and others show a poor preview when it is shared.'),
            self::OPEN_GRAPH_INCOMPLETE->value       => __('The page misses some of og:title, og:description, og:image and og:url, so shared previews lack a title, text or picture.'),
            self::TWITTER_CARD_MISSING->value        => __('The page has no twitter:card tag, so X shows a plain link when it is shared.'),
            self::OG_IMAGE_BROKEN->value             => __('The og:image answers with an error, so shared previews show no picture.'),
            self::MULTIPLE_TITLES->value             => __('The page has more than one title tag. Google may use either one.'),
            self::MULTIPLE_META_DESCRIPTIONS->value  => __('The page has more than one meta description. Google may use either one or neither.'),
            self::CONFLICTING_CANONICALS->value      => __('The page has several canonical tags with different URLs, so Google ignores them all.'),
            self::HEAD_TAGS_IN_BODY->value           => __('A title, meta description, robots or canonical tag sits in the body, where Google ignores it. Often caused by an element that does not belong in the head closing it early, or by a whole HTML document pasted into a block.'),
            self::SITEMAP_NON_200->value             => __('The sitemap lists a URL that redirects, is broken or did not answer. Sitemaps should list only the final, working URLs.'),
            self::SITEMAP_CANONICALISED->value       => __('The sitemap lists a URL that names another URL as canonical. List the canonical URL instead.'),
            self::IN_MULTIPLE_SITEMAPS->value        => __('The same URL appears in several sitemap files.'),
            self::SITEMAP_TOO_LARGE->value           => __('A sitemap file lists more than :urls URLs or is larger than 50 MB, so Google reads only part of it. Shown on the home page.', ['urls' => number_format(AuditWebsite::SITEMAP_MAX_URLS)]),
            self::PAGINATION_TO_BROKEN->value        => __('The rel="next" or rel="prev" URL does not answer with 200.'),
            self::PAGINATION_NOT_LINKED->value       => __('The rel="next" or rel="prev" URL has no ordinary link on the page, so Google may not find it.'),
            self::PAGINATION_LOOP->value             => __('The rel="next" or rel="prev" URL is the page itself.'),
            self::TITLE_OVER_PIXELS->value           => __('The title is wider than :pixels pixels, so Google cuts it off in results even if its character count looks fine.', ['pixels' => SerpPixelWidth::TITLE_MAX_PIXELS]),
            self::META_DESCRIPTION_OVER_PIXELS->value => __('The meta description is wider than :pixels pixels, so Google cuts it off in results.', ['pixels' => SerpPixelWidth::DESCRIPTION_MAX_PIXELS]),
            self::H1_TOO_LONG->value                 => __('The h1 is longer than :length characters, which makes the main subject of the page hard to read at a glance.', ['length' => self::HEADING_MAX_LENGTH]),
            self::H1_NOT_FIRST_HEADING->value        => __('Another heading comes before the h1, so the page outline does not start with its main subject.'),
            self::ALT_TEXT_IN_H1->value              => __('The h1 holds an image, so its text for search engines is the image alt text.'),
            self::H2_TOO_LONG->value                 => __('One of the first two h2 headings is longer than :length characters.', ['length' => self::HEADING_MAX_LENGTH]),
            self::DUPLICATE_H2->value                => __('The first h2 of the page is the same as on other indexable pages, often a heading every page shares.'),
            self::HEADING_LEVEL_SKIPPED->value       => __('A heading jumps more than one level down (for example h2 to h4), which breaks the outline screen readers and search engines read.'),
            self::ALT_TEXT_TOO_LONG->value           => __('Some images have an alt text longer than :length characters, which screen readers read out in full.', ['length' => self::ALT_TEXT_MAX_LENGTH]),
            self::READABILITY_DIFFICULT->value       => __('The English text scores between :very and :difficult on the Flesch reading ease scale: long sentences and long words.', ['very' => self::READING_EASE_VERY_DIFFICULT, 'difficult' => self::READING_EASE_DIFFICULT]),
            self::READABILITY_VERY_DIFFICULT->value  => __('The English text scores under :very on the Flesch reading ease scale, a level of academic papers.', ['very' => self::READING_EASE_VERY_DIFFICULT]),
            self::CANONICAL_RELATIVE->value          => __('The canonical is written as a relative URL. It works, but a mistake in the base URL sends search engines to the wrong page.'),
            self::CANONICAL_WITH_FRAGMENT->value     => __('The canonical URL contains a #fragment, which search engines ignore or treat as a different URL.'),
            self::CANONICAL_UNLINKED->value          => __('The URL named as canonical is not linked from any crawled page, so search engines find it only through the canonical.'),
            self::HREFLANG_MISSING_X_DEFAULT->value  => __('The page lists language versions but no x-default for visitors whose language is not in the list.'),
            self::HREFLANG_TO_NON_CANONICAL->value   => __('A language version listed in hreflang names another URL as canonical, so Google may ignore the alternate.'),
            self::HREFLANG_TO_NOINDEX->value         => __('A language version listed in hreflang tells search engines not to index it.'),
            self::NOSNIPPET_PAGE->value              => __('A nosnippet or max-snippet:0 directive stops Google showing any text under the title in results.'),
            self::NOIMAGEINDEX_PAGE->value           => __('A noimageindex directive keeps the images of the page out of Google Images.'),
            self::UNAVAILABLE_AFTER_PAGE->value      => __('An unavailable_after directive tells Google to drop the page from results after a date.'),
            self::META_REFRESH->value                => __('The page sends visitors on with a meta refresh or a Refresh header instead of a server redirect.'),
            self::BROKEN_BOOKMARK->value             => __('A #fragment link points to an id the page does not have, so it goes nowhere.'),
            self::JAVASCRIPT_LINKS->value            => __('Some links have a javascript: address, which search engines cannot follow.'),
            self::HIGH_INTERNAL_OUTLINKS->value      => __('The page links to more than :count pages of the website, which spreads its link value thin.', ['count' => number_format(self::MAX_INTERNAL_OUTLINKS)]),
            self::HIGH_EXTERNAL_OUTLINKS->value      => __('The page links to more than :count pages of other websites.', ['count' => number_format(self::MAX_EXTERNAL_OUTLINKS)]),
            self::PROTOCOL_RELATIVE_LINKS->value     => __('Some links or resources start with // instead of https://, which loads them over http on an http page.'),
            self::LINKS_WITH_TRACKING_PARAMETERS->value => __('Links between pages of the website carry utm_ or click-id parameters, which create duplicate URLs and overwrite the visit\'s real source in analytics.'),
            self::URL_REPETITIVE_PATH->value         => __('A folder name appears twice in the URL path, often a sign of a relative link resolved twice.'),
            self::URL_CONTAINS_SPACE->value          => __('The URL contains a space, which links and shares break on.'),
            self::MISSING_DOCTYPE->value             => __('The page does not start with <!DOCTYPE html>, so browsers render it in quirks mode.'),
            self::MISSING_HEAD->value                => __('The page has no <head> tag.'),
            self::MULTIPLE_HEADS->value              => __('The page has more than one <head> tag, and tags in the second are read as body.'),
            self::MISSING_BODY->value                => __('The page has no <body> tag.'),
            self::MULTIPLE_BODIES->value             => __('The page has more than one <body> tag.'),
            self::INVALID_HEAD_ELEMENTS->value       => __('The head contains elements that belong in the body. Browsers end the head there, so the canonical, hreflang and meta tags after it are ignored.'),
            self::MISSING_VIEWPORT->value            => __('The page has no meta viewport, so phones show it zoomed out at desktop width.'),
            self::MISSING_CHARSET->value             => __('Neither a meta charset nor the Content-Type header says which character set the page uses, so accents may show garbled.'),
            self::HTML_TOO_LARGE->value              => __('The HTML of the page is larger than :mb MB. Google reads only the first :mb MB.', ['mb' => self::HTML_MAX_MB]),
            self::OPEN_GRAPH_MULTIPLE->value         => __('The same Open Graph or Twitter tag appears more than once, and each network picks one of them.'),
            self::OG_IMAGE_RELATIVE->value           => __('og:image is a relative URL, which social networks do not resolve, so shares show no image.'),
            self::OG_IMAGE_TOO_LARGE->value          => __('The og:image file is larger than :mb MB, which some networks refuse.', ['mb' => self::OG_IMAGE_MAX_MB]),
            self::DUPLICATE_OG_TITLE->value          => __('Another indexable page has the same og:title, so their shares look the same.'),
            self::DUPLICATE_OG_DESCRIPTION->value    => __('Another indexable page has the same og:description.'),
            self::STRUCTURED_DATA_WARNINGS->value    => __('The structured data is valid but leaves out fields Google recommends, so the result may show fewer details.'),
            self::MISSING_PRODUCT_STRUCTURED_DATA->value => __('The page is a product but has no Product or ProductGroup JSON-LD, so Google cannot show its price, stock or rating in results.'),
            self::POOR_LCP->value                    => __('For a quarter of real visitors in the last :days days, the main content took longer than :seconds seconds to appear.', ['days' => EnrichCrawlPages::DAYS, 'seconds' => IndexWebpagesPageSpeed::LCP_LIMITS[1] / 1000]),
            self::POOR_INP->value                    => __('For a quarter of real visitors in the last :days days, the page took longer than :ms ms to respond after a click or tap.', ['days' => EnrichCrawlPages::DAYS, 'ms' => IndexWebpagesPageSpeed::INP_LIMITS[1]]),
            self::POOR_CLS->value                    => __('For a quarter of real visitors in the last :days days, the layout shifted more than :cls while loading, so they clicked the wrong thing.', ['days' => EnrichCrawlPages::DAYS, 'cls' => IndexWebpagesPageSpeed::CLS_LIMITS[1]]),
            self::INDEXABLE_WITHOUT_IMPRESSIONS->value => __('Search Console recorded no impression for this indexable page in the last :days days: Google may not index it, or it ranks for nothing.', ['days' => EnrichCrawlPages::DAYS]),
            self::NON_INDEXABLE_WITH_CLICKS->value   => __('The page tells search engines not to index it, but Search Console recorded clicks in the last :days days. Removing it from Google loses that traffic.', ['days' => EnrichCrawlPages::DAYS]),
            self::INDEXABLE_WITHOUT_VISITS->value    => __('No visitor opened this indexable page in the last :days days.', ['days' => EnrichCrawlPages::DAYS]),
            self::NON_INDEXABLE_WITH_VISITS->value   => __('Visitors opened this page in the last :days days, but it tells search engines not to index it.', ['days' => EnrichCrawlPages::DAYS]),
        ];
    }

    public static function howToFix(): array
    {
        return [
            self::HTTP_4XX->value                    => __('Fix or remove the links pointing to the URL, or redirect it to the closest live page.'),
            self::HTTP_5XX->value                    => __('Open the URL to reproduce the error and check the application logs for it. Crawl again once it answers 200.'),
            self::FETCH_FAILED->value                => __('Open the URL in a browser. If it loads, the server was busy during the crawl: crawl again with a lower speed.'),
            self::REDIRECT_LOOP->value               => __('Change the redirects so the chain ends on a page that answers 200.'),
            self::MISSING_TITLE->value               => __('Write a title that names what the page offers, between :min and :max characters.', ['min' => self::TITLE_MIN_LENGTH, 'max' => self::TITLE_MAX_LENGTH]),
            self::CANONICAL_TO_BROKEN->value         => __('Point the canonical to the live URL of the page, usually the page itself.'),
            self::REDIRECT_CHAIN->value              => __('Point the first redirect straight to the final URL, and update links to use the final URL.'),
            self::DUPLICATE_TITLE->value             => __('Give each page a title that says what makes it different, such as the product or family name.'),
            self::DUPLICATE_META_DESCRIPTION->value  => __('Write a meta description for each page that summarises its own content.'),
            self::MISSING_META_DESCRIPTION->value    => __('Write a meta description of :min to :max characters that makes searchers want to click.', ['min' => self::META_DESCRIPTION_MIN_LENGTH, 'max' => self::META_DESCRIPTION_MAX_LENGTH]),
            self::MISSING_H1->value                  => __('Add one h1 heading that says what the page is about, usually the product or family name.'),
            self::MULTIPLE_H1->value                 => __('Keep one h1 for the main subject and turn the other h1 headings into h2.'),
            self::MISSING_CANONICAL->value           => __('Add a canonical link in the head that points to the page\'s own URL.'),
            self::NOINDEX_IN_SITEMAP->value          => __('Remove the page from the sitemap, or remove the noindex if the page should be found in search.'),
            self::SLOW_RESPONSE->value               => __('Check what makes the page slow to render (large product lists, slow queries) and whether it is served from cache.'),
            self::IMAGES_WITHOUT_ALT->value          => __('Add an alt text to every image that describes it. Decorative images take an empty alt="".'),
            self::TITLE_TOO_LONG->value              => __('Shorten the title to :max characters or fewer, keeping the most important words first.', ['max' => self::TITLE_MAX_LENGTH]),
            self::TITLE_TOO_SHORT->value             => __('Make the title more descriptive, between :min and :max characters.', ['min' => self::TITLE_MIN_LENGTH, 'max' => self::TITLE_MAX_LENGTH]),
            self::META_DESCRIPTION_TOO_LONG->value   => __('Shorten the meta description to :max characters or fewer.', ['max' => self::META_DESCRIPTION_MAX_LENGTH]),
            self::META_DESCRIPTION_TOO_SHORT->value  => __('Expand the meta description to at least :min characters.', ['min' => self::META_DESCRIPTION_MIN_LENGTH]),
            self::CANONICAL_TO_OTHER_PAGE->value     => __('If the page is unique, point its canonical to itself. If it is a variant, no change is needed.'),
            self::NOT_IN_SITEMAP->value              => __('Add the page to the sitemap, or make it non-indexable if it should not be found in search.'),
            self::LINKED_REDIRECT->value             => __('Change the links to point to the final URL the redirect leads to.'),
            self::HREFLANG_TO_BROKEN->value          => __('Point the hreflang link to the live URL of the language version, or remove it.'),
            self::HREFLANG_INVALID_CODE->value       => __('Use an ISO 639-1 language code, optionally with an ISO 3166-1 region (for example en-gb), or x-default.'),
            self::HREFLANG_CONFLICTING_CODE->value   => __('Use each language code for one URL only.'),
            self::HREFLANG_MISSING_SELF->value       => __('Add an hreflang link from the page to itself with its own language code.'),
            self::HREFLANG_MISSING_RETURN->value     => __('Add an hreflang link back to this page on the alternate page.'),
            self::INVALID_STRUCTURED_DATA->value     => __('Test the page in Google\'s Rich Results Test and add the missing or invalid fields.'),
            self::NOINDEX_PAGE->value                => __('Remove the noindex if the page should be found in search.'),
            self::NOFOLLOW_PAGE->value               => __('Remove the nofollow from the robots meta tag unless the page\'s links must not be followed.'),
            self::CANONICAL_TO_NON_INDEXABLE->value  => __('Point the canonical to an indexable page, usually the page itself.'),
            self::SOFT_404->value                    => __('If the page no longer exists, make it answer 404 or 410, or redirect it. Otherwise change the title and h1 so they do not read as an error.'),
            self::EXACT_DUPLICATE->value             => __('Make the content of each page unique, or point the canonical of the copies to the main page.'),
            self::NEAR_DUPLICATE->value              => __('Add content that makes each page different, or point the canonical of the copies to the main page.'),
            self::LOW_CONTENT->value                 => __('Add useful text about what the page offers, at least :words words.', ['words' => self::LOW_CONTENT_WORDS]),
            self::LOREM_IPSUM->value                 => __('Replace the placeholder text with real content.'),
            self::DUPLICATE_H1->value                => __('Give each page an h1 that says what makes it different.'),
            self::MISSING_H2->value                  => __('Add h2 subheadings to structure the content.'),
            self::TITLE_SAME_AS_H1->value            => __('Use the title and the h1 to say slightly different things, so the page matches more searches.'),
            self::ORPHAN_PAGE->value                 => __('Link to the page from a related page, menu or family, or remove it from the sitemap if it is no longer needed.'),
            self::HIGH_CRAWL_DEPTH->value            => __('Link to the page from a page closer to the home page, so it is reached in :clicks clicks or fewer.', ['clicks' => self::HIGH_CRAWL_DEPTH_CLICKS]),
            self::NO_INTERNAL_OUTLINKS->value        => __('Add links from the page to related pages of the website.'),
            self::INTERNAL_NOFOLLOW_LINKS->value     => __('Remove rel="nofollow" from links to pages of this website.'),
            self::EMPTY_ANCHOR_TEXT->value           => __('Add text to the link, or an aria-label or an alt text on the image it wraps.'),
            self::NON_DESCRIPTIVE_ANCHOR_TEXT->value => __('Replace generic link text such as "click here" with words that describe the target page.'),
            self::LOCALHOST_LINKS->value             => __('Replace the links to development URLs with the live URLs.'),
            self::UNSAFE_BLANK_LINKS->value          => __('Add rel="noopener" to links that open in a new tab.'),
            self::BROKEN_EXTERNAL_LINKS->value       => __('Update the links to the external page\'s new address, or remove them.'),
            self::BROKEN_IMAGES->value               => __('Upload the image again or change the page to use an image that exists.'),
            self::LARGE_IMAGES->value                => __('Resize and compress the image to under :size KB, or serve it in a modern format such as WebP.', ['size' => self::LARGE_IMAGE_KB]),
            self::IMAGES_WITHOUT_SIZE->value         => __('Add width and height attributes to the image so the page does not jump while it loads.'),
            self::URL_UPPERCASE->value               => __('Use lowercase URLs and redirect the uppercase ones to them.'),
            self::URL_UNDERSCORES->value             => __('Use hyphens instead of underscores to separate words, and redirect the old URLs.'),
            self::URL_MULTIPLE_SLASHES->value        => __('Fix the links that produce the double slash, and redirect the URL to the single-slash one.'),
            self::URL_NON_ASCII->value               => __('Use plain ASCII characters in the URL, and redirect the old URL.'),
            self::URL_TOO_LONG->value                => __('Shorten the URL to :length characters or fewer, and redirect the old URL.', ['length' => self::URL_MAX_LENGTH]),
            self::LINKS_WITH_PARAMETERS->value       => __('Link to the clean URL without parameters where one exists.'),
            self::INTERNAL_HTTP_LINKS->value         => __('Change the links to use https.'),
            self::MIXED_CONTENT->value               => __('Load the images, scripts and styles over https.'),
            self::INSECURE_FORMS->value              => __('Make the form submit to an https URL.'),
            self::MISSING_SECURITY_HEADERS->value    => __('Add the missing headers in the server or Cloudflare configuration.'),
            self::OPEN_GRAPH_MISSING->value          => __('Add og:title, og:description, og:image and og:url tags to the page.'),
            self::OPEN_GRAPH_INCOMPLETE->value       => __('Add the missing Open Graph tags listed in the details.'),
            self::TWITTER_CARD_MISSING->value        => __('Add a twitter:card meta tag, usually summary_large_image.'),
            self::OG_IMAGE_BROKEN->value             => __('Point og:image to an image that exists.'),
            self::MULTIPLE_TITLES->value             => __('Keep one title tag in the head and remove the others.'),
            self::MULTIPLE_META_DESCRIPTIONS->value  => __('Keep one meta description in the head and remove the others.'),
            self::CONFLICTING_CANONICALS->value      => __('Keep one canonical link and remove the others.'),
            self::HEAD_TAGS_IN_BODY->value           => __('Move the tag into the head, or remove it from the body.'),
            self::SITEMAP_NON_200->value             => __('Remove the URL from the sitemap, or list its final URL instead.'),
            self::SITEMAP_CANONICALISED->value       => __('List the canonical URL in the sitemap instead of this one.'),
            self::IN_MULTIPLE_SITEMAPS->value        => __('List the URL in one sitemap only.'),
            self::SITEMAP_TOO_LARGE->value           => __('Split the sitemap into files of at most :urls URLs and 50 MB each.', ['urls' => number_format(AuditWebsite::SITEMAP_MAX_URLS)]),
            self::PAGINATION_TO_BROKEN->value        => __('Point rel="next" and rel="prev" to pages that answer 200.'),
            self::PAGINATION_NOT_LINKED->value       => __('Add a normal link to the next and previous pages in the page body.'),
            self::PAGINATION_LOOP->value             => __('Fix rel="next" and rel="prev" so the sequence does not lead back to an earlier page.'),
            self::TITLE_OVER_PIXELS->value           => __('Shorten the title or use narrower words until it fits in :pixels pixels; capital letters take the most room.', ['pixels' => SerpPixelWidth::TITLE_MAX_PIXELS]),
            self::META_DESCRIPTION_OVER_PIXELS->value => __('Shorten the meta description until it fits in :pixels pixels.', ['pixels' => SerpPixelWidth::DESCRIPTION_MAX_PIXELS]),
            self::H1_TOO_LONG->value                 => __('Shorten the h1 to :length characters or fewer and move the rest into the text.', ['length' => self::HEADING_MAX_LENGTH]),
            self::H1_NOT_FIRST_HEADING->value        => __('Make the h1 the first heading of the page, or turn the earlier heading into plain text.'),
            self::ALT_TEXT_IN_H1->value              => __('Write the h1 as text, and keep the image beside it.'),
            self::H2_TOO_LONG->value                 => __('Shorten the h2 to :length characters or fewer.', ['length' => self::HEADING_MAX_LENGTH]),
            self::DUPLICATE_H2->value                => __('Use h2 headings that describe the content of this page, and style shared blocks without heading tags.'),
            self::HEADING_LEVEL_SKIPPED->value       => __('Use the next heading level down, or change the heading\'s style instead of its level.'),
            self::ALT_TEXT_TOO_LONG->value           => __('Keep alt texts to a short description of the image, :length characters or fewer.', ['length' => self::ALT_TEXT_MAX_LENGTH]),
            self::READABILITY_DIFFICULT->value       => __('Use shorter sentences and simpler words.'),
            self::READABILITY_VERY_DIFFICULT->value  => __('Split long sentences and replace long words with everyday ones.'),
            self::CANONICAL_RELATIVE->value          => __('Write the canonical as a full URL with https and the domain.'),
            self::CANONICAL_WITH_FRAGMENT->value     => __('Remove the #fragment from the canonical URL.'),
            self::CANONICAL_UNLINKED->value          => __('Link to the canonical URL from the website, for example from its family or the menu.'),
            self::HREFLANG_MISSING_X_DEFAULT->value  => __('Add an hreflang x-default link to the version for every other visitor.'),
            self::HREFLANG_TO_NON_CANONICAL->value   => __('List the canonical URL of each language version in hreflang.'),
            self::HREFLANG_TO_NOINDEX->value         => __('Remove the noindex from the language version, or remove it from hreflang.'),
            self::NOSNIPPET_PAGE->value              => __('Remove nosnippet unless the text must not appear in results.'),
            self::NOIMAGEINDEX_PAGE->value           => __('Remove noimageindex if the product images should be found in image search.'),
            self::UNAVAILABLE_AFTER_PAGE->value      => __('Check the date is right, or remove the directive if the page stays.'),
            self::META_REFRESH->value                => __('Replace the refresh with a 301 redirect and link to the final URL.'),
            self::BROKEN_BOOKMARK->value             => __('Add the id to the element it should jump to, or fix the link.'),
            self::JAVASCRIPT_LINKS->value            => __('Use a real URL in href, and keep the script for the click.'),
            self::HIGH_INTERNAL_OUTLINKS->value      => __('Link to the most important pages and move the rest into their families or the menu.'),
            self::HIGH_EXTERNAL_OUTLINKS->value      => __('Keep the external links that help the visitor and remove the rest.'),
            self::PROTOCOL_RELATIVE_LINKS->value     => __('Write the links and resources with https://.'),
            self::LINKS_WITH_TRACKING_PARAMETERS->value => __('Remove tracking parameters from internal links; keep them for links in emails and ads only.'),
            self::URL_REPETITIVE_PATH->value         => __('Fix the link that produces it, and redirect the URL to the right one.'),
            self::URL_CONTAINS_SPACE->value          => __('Use hyphens instead of spaces, and redirect the old URL.'),
            self::MISSING_DOCTYPE->value             => __('Start the HTML with <!DOCTYPE html>.'),
            self::MISSING_HEAD->value                => __('Wrap the title, meta tags and links in a <head> element.'),
            self::MULTIPLE_HEADS->value              => __('Keep one <head> and move its tags there.'),
            self::MISSING_BODY->value                => __('Wrap the page content in a <body> element.'),
            self::MULTIPLE_BODIES->value             => __('Keep one <body> element.'),
            self::INVALID_HEAD_ELEMENTS->value       => __('Move the listed elements into the body, after the head.'),
            self::MISSING_VIEWPORT->value            => __('Add <meta name="viewport" content="width=device-width, initial-scale=1">.'),
            self::MISSING_CHARSET->value             => __('Add <meta charset="utf-8"> at the top of the head.'),
            self::HTML_TOO_LARGE->value              => __('Reduce the HTML: fewer products per page, less inline data.', ['mb' => self::HTML_MAX_MB]),
            self::OPEN_GRAPH_MULTIPLE->value         => __('Keep one of each tag.'),
            self::OG_IMAGE_RELATIVE->value           => __('Write og:image as a full URL with https and the domain.'),
            self::OG_IMAGE_TOO_LARGE->value          => __('Use a compressed image under :mb MB, ideally 1200 x 630 pixels.', ['mb' => self::OG_IMAGE_MAX_MB]),
            self::DUPLICATE_OG_TITLE->value          => __('Give each page an og:title that names its own content.'),
            self::DUPLICATE_OG_DESCRIPTION->value    => __('Give each page an og:description about its own content.'),
            self::STRUCTURED_DATA_WARNINGS->value    => __('Add the fields listed in the details, such as brand, sku or availability.'),
            self::MISSING_PRODUCT_STRUCTURED_DATA->value => __('Output Product structured data with name, offers (price, currency, availability) and image on the product page template.'),
            self::POOR_LCP->value                    => __('Make the largest image or text block load sooner: smaller images, preload the hero image, fewer render-blocking scripts.', ['days' => EnrichCrawlPages::DAYS, 'seconds' => IndexWebpagesPageSpeed::LCP_LIMITS[1] / 1000]),
            self::POOR_INP->value                    => __('Break up long JavaScript tasks and run less code when the page loads.'),
            self::POOR_CLS->value                    => __('Give images and banners a fixed width and height, and reserve space for content that loads late.'),
            self::INDEXABLE_WITHOUT_IMPRESSIONS->value => __('Check the page in Search Console URL Inspection, link to it from related pages and give it unique content.'),
            self::NON_INDEXABLE_WITH_CLICKS->value   => __('If the page should be found, remove the noindex or fix the canonical. If not, redirect it to the page that should rank.'),
            self::INDEXABLE_WITHOUT_VISITS->value    => __('Check whether the page is still needed: link to it from related pages, or redirect it to a page people use.', ['days' => EnrichCrawlPages::DAYS]),
            self::NON_INDEXABLE_WITH_VISITS->value   => __('If people look for this page, make it indexable so search engines can send more of them.'),
        ];
    }
}
