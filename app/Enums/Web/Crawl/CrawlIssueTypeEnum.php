<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Enums\Web\Crawl;

use App\Enums\EnumHelperTrait;

enum CrawlIssueTypeEnum: string
{
    use EnumHelperTrait;

    case HTTP_4XX                   = 'http_4xx';
    case HTTP_5XX                   = 'http_5xx';
    case FETCH_FAILED               = 'fetch_failed';
    case REDIRECT_LOOP              = 'redirect_loop';
    case MISSING_TITLE              = 'missing_title';
    case CANONICAL_TO_BROKEN        = 'canonical_to_broken';
    case REDIRECT_CHAIN             = 'redirect_chain';
    case DUPLICATE_TITLE            = 'duplicate_title';
    case DUPLICATE_META_DESCRIPTION = 'duplicate_meta_description';
    case MISSING_META_DESCRIPTION   = 'missing_meta_description';
    case MISSING_H1                 = 'missing_h1';
    case MULTIPLE_H1                = 'multiple_h1';
    case MISSING_CANONICAL          = 'missing_canonical';
    case NOINDEX_IN_SITEMAP         = 'noindex_in_sitemap';
    case SLOW_RESPONSE              = 'slow_response';
    case IMAGES_WITHOUT_ALT         = 'images_without_alt';
    case TITLE_TOO_LONG             = 'title_too_long';
    case TITLE_TOO_SHORT            = 'title_too_short';
    case META_DESCRIPTION_TOO_LONG  = 'meta_description_too_long';
    case META_DESCRIPTION_TOO_SHORT = 'meta_description_too_short';
    case CANONICAL_TO_OTHER_PAGE    = 'canonical_to_other_page';
    case NOT_IN_SITEMAP             = 'not_in_sitemap';
    case LINKED_REDIRECT            = 'linked_redirect';
    case HREFLANG_TO_BROKEN         = 'hreflang_to_broken';
    case HREFLANG_INVALID_CODE      = 'hreflang_invalid_code';
    case HREFLANG_CONFLICTING_CODE  = 'hreflang_conflicting_code';
    case HREFLANG_MISSING_SELF      = 'hreflang_missing_self';
    case HREFLANG_MISSING_RETURN    = 'hreflang_missing_return';

    public const int TITLE_MAX_LENGTH            = 60;
    public const int TITLE_MIN_LENGTH            = 20;
    public const int META_DESCRIPTION_MAX_LENGTH = 160;
    public const int META_DESCRIPTION_MIN_LENGTH = 70;
    public const int SLOW_RESPONSE_MS            = 3000;

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
            self::HREFLANG_MISSING_RETURN => CrawlIssueSeverityEnum::WARNING,

            self::TITLE_TOO_LONG,
            self::TITLE_TOO_SHORT,
            self::META_DESCRIPTION_TOO_LONG,
            self::META_DESCRIPTION_TOO_SHORT,
            self::CANONICAL_TO_OTHER_PAGE,
            self::NOT_IN_SITEMAP,
            self::LINKED_REDIRECT => CrawlIssueSeverityEnum::NOTICE,
        };
    }

    public static function labels(): array
    {
        return [
            self::HTTP_4XX->value                   => __('Page returns 4xx'),
            self::HTTP_5XX->value                   => __('Page returns 5xx'),
            self::FETCH_FAILED->value               => __('Page could not be fetched'),
            self::REDIRECT_LOOP->value              => __('Redirect loop'),
            self::MISSING_TITLE->value              => __('Missing title'),
            self::CANONICAL_TO_BROKEN->value        => __('Canonical points to a broken or redirecting URL'),
            self::REDIRECT_CHAIN->value             => __('Redirect chain'),
            self::DUPLICATE_TITLE->value            => __('Duplicate title'),
            self::DUPLICATE_META_DESCRIPTION->value => __('Duplicate meta description'),
            self::MISSING_META_DESCRIPTION->value   => __('Missing meta description'),
            self::MISSING_H1->value                 => __('Missing h1'),
            self::MULTIPLE_H1->value                => __('More than one h1'),
            self::MISSING_CANONICAL->value          => __('Missing canonical'),
            self::NOINDEX_IN_SITEMAP->value         => __('Noindex page listed in the sitemap'),
            self::SLOW_RESPONSE->value              => __('Slow response'),
            self::IMAGES_WITHOUT_ALT->value         => __('Images without alt text'),
            self::TITLE_TOO_LONG->value             => __('Title too long'),
            self::TITLE_TOO_SHORT->value            => __('Title too short'),
            self::META_DESCRIPTION_TOO_LONG->value  => __('Meta description too long'),
            self::META_DESCRIPTION_TOO_SHORT->value => __('Meta description too short'),
            self::CANONICAL_TO_OTHER_PAGE->value    => __('Canonical points to another page'),
            self::NOT_IN_SITEMAP->value             => __('Indexable page missing from the sitemap'),
            self::LINKED_REDIRECT->value            => __('Internal links point to a redirect'),
            self::HREFLANG_TO_BROKEN->value         => __('Hreflang points to a broken or redirecting URL'),
            self::HREFLANG_INVALID_CODE->value      => __('Invalid hreflang code'),
            self::HREFLANG_CONFLICTING_CODE->value  => __('Hreflang code used for more than one URL'),
            self::HREFLANG_MISSING_SELF->value      => __('Hreflang does not list the page itself'),
            self::HREFLANG_MISSING_RETURN->value    => __('Hreflang alternate does not link back'),
        ];
    }

    public static function descriptions(): array
    {
        return [
            self::HTTP_4XX->value                   => __('The page answers with a 4xx status. Visitors and Google that follow links to it reach a dead end.'),
            self::HTTP_5XX->value                   => __('The server failed to render the page. Google drops pages that keep failing.'),
            self::FETCH_FAILED->value               => __('The request timed out or the connection failed, so nothing could be checked.'),
            self::REDIRECT_LOOP->value              => __('The redirects lead back to a URL already in the chain, so the page never loads.'),
            self::MISSING_TITLE->value              => __('The page has no title tag. Google writes its own, usually a poor one.'),
            self::CANONICAL_TO_BROKEN->value        => __('The canonical URL does not answer with 200, so Google may ignore it or drop the page.'),
            self::REDIRECT_CHAIN->value             => __('The URL redirects more than once before reaching a page. Each hop slows the page and wastes crawl budget.'),
            self::DUPLICATE_TITLE->value            => __('Another indexable page has the same title, so Google cannot tell them apart in results.'),
            self::DUPLICATE_META_DESCRIPTION->value => __('Another indexable page has the same meta description.'),
            self::MISSING_META_DESCRIPTION->value   => __('The page has no meta description. Google picks a snippet from the page text instead.'),
            self::MISSING_H1->value                 => __('The page has no h1 heading.'),
            self::MULTIPLE_H1->value                => __('The page has more than one h1 heading.'),
            self::MISSING_CANONICAL->value          => __('The page does not say which URL is the main one, so duplicates with other URLs are possible.'),
            self::NOINDEX_IN_SITEMAP->value         => __('The sitemap asks Google to index a page that tells Google not to index it.'),
            self::SLOW_RESPONSE->value              => __('The server took more than :seconds seconds to answer.', ['seconds' => self::SLOW_RESPONSE_MS / 1000]),
            self::IMAGES_WITHOUT_ALT->value         => __('Some images have no alt attribute, so screen readers and image search cannot describe them.'),
            self::TITLE_TOO_LONG->value             => __('The title is longer than :length characters and is likely cut off in results.', ['length' => self::TITLE_MAX_LENGTH]),
            self::TITLE_TOO_SHORT->value            => __('The title is shorter than :length characters.', ['length' => self::TITLE_MIN_LENGTH]),
            self::META_DESCRIPTION_TOO_LONG->value  => __('The meta description is longer than :length characters and is likely cut off.', ['length' => self::META_DESCRIPTION_MAX_LENGTH]),
            self::META_DESCRIPTION_TOO_SHORT->value => __('The meta description is shorter than :length characters.', ['length' => self::META_DESCRIPTION_MIN_LENGTH]),
            self::CANONICAL_TO_OTHER_PAGE->value    => __('The page names another URL as canonical, so Google indexes that URL instead. Fine for variants, wrong for unique pages.'),
            self::NOT_IN_SITEMAP->value             => __('Google can index the page, but the sitemap does not list it.'),
            self::LINKED_REDIRECT->value            => __('Links inside the website point to a URL that redirects. Linking to the final URL saves a hop.'),
            self::HREFLANG_TO_BROKEN->value         => __('A language version listed in hreflang does not answer with 200. Google ignores the whole set of alternates when one of them is broken.'),
            self::HREFLANG_INVALID_CODE->value      => __('The hreflang value is not a language code (ISO 639-1), a language and region (for example en-GB) or x-default, so Google ignores it.'),
            self::HREFLANG_CONFLICTING_CODE->value  => __('The same hreflang code points to different URLs, so Google cannot tell which one to show.'),
            self::HREFLANG_MISSING_SELF->value      => __('The page lists other language versions but not itself. Every version must list all versions, itself included.'),
            self::HREFLANG_MISSING_RETURN->value    => __('A language version listed here does not list this page back, so Google ignores the pair. Checked against the latest audit of the other website.'),
        ];
    }
}
