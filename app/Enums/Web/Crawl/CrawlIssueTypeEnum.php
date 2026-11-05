<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Enums\Web\Crawl;

use App\Actions\Web\Crawl\AuditWebsite;
use App\Enums\EnumHelperTrait;

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

    public const int TITLE_MAX_LENGTH            = 60;
    public const int TITLE_MIN_LENGTH            = 20;
    public const int META_DESCRIPTION_MAX_LENGTH = 160;
    public const int META_DESCRIPTION_MIN_LENGTH = 70;
    public const int SLOW_RESPONSE_MS            = 3000;
    public const int LOW_CONTENT_WORDS           = 200;
    public const int HIGH_CRAWL_DEPTH_CLICKS     = 4;
    public const int LARGE_IMAGE_KB              = 200;
    public const int URL_MAX_LENGTH              = 115;

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
            self::INVALID_STRUCTURED_DATA => CrawlIssueSeverityEnum::WARNING,

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
            self::LINKED_REDIRECT => CrawlIssueSeverityEnum::NOTICE,
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
        ];
    }
}
