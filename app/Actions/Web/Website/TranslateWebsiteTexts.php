<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 23:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website;

use App\Actions\Helpers\ClearCacheByWildcard;
use App\Actions\Helpers\Translations\Translate;
use App\Actions\Traits\AsUniqueUntilProcessingAction;
use App\Actions\Web\Webpage\BreakWebpageCache;
use App\Enums\Web\Crawl\CrawlTriggerEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Helpers\WebsiteLanguage;
use App\Helpers\WebsiteTexts;
use App\Models\Catalogue\ProductCategory;
use App\Models\Helpers\Language;
use App\Models\Web\Banner;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use App\Models\Web\WebsiteTextTranslation;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;

/**
 * Machine translates the website texts (web blocks, header, top bar, menu, footer, sidebar, the marginal templates,
 * banner slides, webpage titles, descriptions, breadcrumb labels and share image alts) that have no translation yet into every other language the website shows
 * (INI-510). Only new texts cost anything: a text keeps its row for as long as its English is unchanged, and an
 * existing row, machine or human, is never replaced. Runs after a webpage, a website marginal or a banner is published,
 * and breaks only the caches that show what it wrote. A banner breaks none: its English reaches cached pages when they
 * expire, and its German rows are written well before that.
 */
class TranslateWebsiteTexts implements ShouldBeUniqueUntilProcessing
{
    use AsUniqueUntilProcessingAction;

    public string $commandSignature = 'website:translate_texts {website : website slug} {--locale= : only this language} {--dry-run : count what would be translated}';

    public string $jobQueue = 'low-priority';

    private const int PIECE_CHARACTERS = 6000;

    /**
     * Marginals that reach the visitor only through /json calls, never inside a page's HTML.
     */
    private const array JSON_MARGINALS = ['footer', 'sidebar'];

    public function getJobUniqueId(Website $website, ?Webpage $webpage = null, ?string $marginal = null, ?Banner $banner = null): string
    {
        return $website->id.'-'.($webpage?->id ?? $marginal ?? ($banner ? 'banner-'.$banner->id : 'all'));
    }

    /**
     * $marginal: the published website layout part; its texts are all in the website's own layout (template
     * marginals are copied from it into the pages), so the webpages need not be read.
     */
    public static function dispatchIfTranslated(Website $website, ?Webpage $webpage = null, ?string $marginal = null, ?Banner $banner = null): void
    {
        if (count($website->shop->websiteLanguageCodes()) > 1) {
            self::dispatch($website, $webpage, $marginal, $banner);
        }
    }

    /**
     * @return array<string, array{texts: int, missing: int, written: int}>
     */
    public function handle(Website $website, ?Webpage $webpage = null, ?string $marginal = null, ?Banner $banner = null, ?string $onlyLocale = null, bool $dryRun = false): array
    {
        $shop    = $website->shop;
        $locales = array_values(array_diff($shop->websiteLanguageCodes(), [$shop->language->code]));
        if ($onlyLocale) {
            $locales = array_values(array_intersect($locales, [$onlyLocale]));
        }
        if (!$locales) {
            return [];
        }

        $texts = match (true) {
            $webpage !== null  => $this->webpageTexts($webpage),
            $marginal !== null => WebsiteTexts::collect($website->published_layout),
            $banner !== null   => WebsiteTexts::collectBanner($banner->compiled_layout),
            default            => $this->websiteTexts($website),
        };

        $stats   = [];
        $written = 0;
        foreach (Language::whereIn('code', $locales)->get() as $language) {
            $existing = WebsiteTextTranslation::where('website_id', $website->id)->where('locale', $language->code)->pluck('source_hash')->flip()->all();
            $missing  = array_diff_key($texts, $existing);
            $done     = ($dryRun || !$missing) ? 0 : $this->translate($website, $missing, $shop->language, $language);

            $written += $done;
            $stats[$language->code] = ['texts' => count($texts), 'missing' => count($missing), 'written' => $done];
        }

        if ($written && !$banner) {
            $this->breakCaches($website, $webpage, $marginal);
        }

        return $stats;
    }

    /**
     * A footer or sidebar text lives only in its /json cache; a webpage's texts in that page (its title also shows in
     * its children's breadcrumbs until their cache expires); the header, menu and templates are in every page.
     */
    private function breakCaches(Website $website, ?Webpage $webpage, ?string $marginal): void
    {
        if ($webpage) {
            BreakWebpageCache::run($webpage);
        } elseif (in_array($marginal, self::JSON_MARGINALS, true)) {
            ClearCacheByWildcard::run("irisData:website:$website->id:*");
        } else {
            BreakWebsiteCache::run($website, CrawlTriggerEnum::WEBSITE_UPDATE);
        }
    }

    /**
     * @return array<string, string>
     */
    public function websiteTexts(Website $website): array
    {
        $texts = WebsiteTexts::collect($website->published_layout);

        foreach (Banner::where('website_id', $website->id)->select(['id', 'compiled_layout'])->get() as $banner) {
            $texts += WebsiteTexts::collectBanner($banner->compiled_layout);
        }

        $website->webpages()->where('state', WebpageStateEnum::LIVE)->with('model')
            ->select(['id', 'website_id', 'title', 'description', 'breadcrumb_label', 'seo_data', 'published_layout', 'model_type', 'model_id'])
            ->chunkById(200, function ($webpages) use (&$texts) {
                foreach ($webpages as $webpage) {
                    $texts += $this->webpageTexts($webpage);
                }
            });

        return $texts;
    }

    /**
     * A title, description, breadcrumb label or share image alt that is the name of the page's product or category
     * follows that name's translation, so only the others are collected here. A product page's breadcrumb shows its
     * code, so its label is not collected.
     *
     * @return array<string, string>
     */
    public function webpageTexts(Webpage $webpage): array
    {
        $texts = WebsiteTexts::collect($webpage->published_layout);
        $name  = $webpage->model?->getAttribute('name');

        $breadcrumbLabel = $webpage->model_type === 'Product' ? null : $webpage->breadcrumb_label;

        foreach ([$webpage->title, $webpage->description, $breadcrumbLabel, data_get($webpage->seo_data, 'image_alt')] as $text) {
            if (is_string($text) && $text !== $name && WebsiteTexts::isWords($text)) {
                $texts[WebsiteTexts::hash($text)] = $text;
            }
        }

        if ($webpage->model instanceof ProductCategory) {
            $texts += WebsiteLanguage::faqTexts($webpage->model->faq);
        }

        return $texts;
    }

    /**
     * Each text goes to the AI masked (see WebsiteTexts::mask), a long one in pieces cut after a closing tag, so a
     * page-long custom HTML block fits the model's answer. A text is kept only when every piece came back with its
     * markup intact and the whole nests as the shop's text did.
     *
     * @param array<string, string> $texts
     */
    private function translate(Website $website, array $texts, Language $from, Language $to): int
    {
        $masked = [];
        $kept   = [];
        $pieces = [];
        foreach ($texts as $hash => $text) {
            [$masked[$hash], $kept[$hash]] = WebsiteTexts::mask($text);
            foreach (WebsiteTexts::pieces($masked[$hash], self::PIECE_CHARACTERS) as $index => $piece) {
                $pieces[$hash][$index] = $piece;
            }
        }

        $toAi = [];
        foreach ($pieces as $hash => $textPieces) {
            foreach ($textPieces as $index => $piece) {
                if (WebsiteTexts::hasWords($piece)) {
                    $toAi["$hash:$index"] = $piece;
                }
            }
        }

        $translatedPieces = [];
        foreach ($this->batches($toAi) as $batch) {
            $translatedPieces += Translate::make()->many($batch, $from, $to, 'sonnet');
        }

        $rows = [];
        foreach ($pieces as $hash => $textPieces) {
            $translated = '';
            foreach ($textPieces as $index => $piece) {
                if (!isset($toAi["$hash:$index"])) {
                    $translated .= $piece;
                    continue;
                }
                $translatedPiece = $translatedPieces["$hash:$index"] ?? null;
                if ($translatedPiece === null || !$this->keepsMarkup($piece, $translatedPiece)) {
                    continue 2;
                }
                $translated .= $translatedPiece;
            }
            if (!WebsiteTexts::nestsLike($masked[$hash], $translated)) {
                continue;
            }

            $rows[] = [
                'website_id'  => $website->id,
                'locale'      => $to->code,
                'source_hash' => $hash,
                'source_text' => $texts[$hash],
                'text'        => WebsiteTexts::unmask($translated, $kept[$hash]),
                'origin'      => WebsiteTextTranslation::MACHINE,
                'created_at'  => now(),
                'updated_at'  => now(),
            ];
        }

        return $rows ? WebsiteTextTranslation::insertOrIgnore($rows) : 0;
    }

    /**
     * @param array<string, string> $texts
     *
     * @return iterable<array<string, string>>
     */
    private function batches(array $texts): iterable
    {
        $batch = [];
        $size  = 0;
        foreach ($texts as $key => $text) {
            if ($batch && $size + mb_strlen($text) > self::PIECE_CHARACTERS) {
                yield $batch;
                $batch = [];
                $size  = 0;
            }
            $batch[$key] = $text;
            $size += mb_strlen($text);
        }
        if ($batch) {
            yield $batch;
        }
    }

    /**
     * A translation must keep every tag (with its data-k reference), every kept <style>/<script>/comment and every
     * {{ placeholder }}; German may order inline tags differently, and only the words between tags and the alt,
     * title, placeholder and aria-label texts may change. Anything else would break the page, so the shop's own
     * text is shown instead.
     */
    public function keepsMarkup(string $source, string $translated): bool
    {
        $marks = function (string $text): array {
            preg_match_all('/<!--.*?-->|<\/?[a-z][^>]*>|\{\{.*?\}\}/is', $text, $matches);
            $marks = array_map(
                fn (string $mark) => (string) preg_replace('/(?<![\w-])(alt|title|placeholder|aria-label)\s*=\s*("[^"]*"|\'[^\']*\')/i', '$1=""', $mark),
                $matches[0]
            );
            sort($marks);

            return $marks;
        };

        return WebsiteTexts::hasWords($translated) && $marks($source) === $marks($translated);
    }

    public function asCommand(Command $command): int
    {
        $website = Website::where('slug', $command->argument('website'))->firstOrFail();
        $stats   = $this->handle($website, null, null, null, $command->option('locale') ?: null, (bool) $command->option('dry-run'));

        if (!$stats) {
            $command->warn('The website shows no language other than its shop\'s.');
        }
        foreach ($stats as $locale => $row) {
            $command->info("$locale: {$row['texts']} texts, {$row['missing']} without translation, {$row['written']} translated");
        }

        return 0;
    }
}
