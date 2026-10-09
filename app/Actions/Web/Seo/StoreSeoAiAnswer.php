<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Actions\Web\Webpage\WithWebpageIdsByPath;
use App\Enums\Web\Website\WebsiteStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Web\SeoAiAnswer;
use App\Models\Web\SeoAiCitation;
use App\Models\Web\SeoAiPrompt;
use App\Models\Web\SeoCompetitor;
use App\Models\Web\Website;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Reads one ChatGPT answer from the DataForSEO LLM Scraper (advanced) into whether it names our brand
 * and the competitors, and which sources it cites. A brand is named when the answer links to its
 * domain or writes one of its names (ours are the shop's AI brand names); image captions do not
 * count. Its place is its entry in the list the answer is built on (its headings, numbered items or
 * bold lines), because the scraper's brand entities carry descriptions, not names.
 */
class StoreSeoAiAnswer
{
    use AsObject;
    use WithWebpageIdsByPath;

    public const string BRAND_NAMES_SETTING = 'seo.ai_brand_names';

    /**
     * @var array<string, Website>|null
     */
    private ?array $websitesByDomain = null;

    /**
     * @var array<int, array<string, int>>
     */
    private array $webpageIdsByPathPerWebsite = [];

    /**
     * @return array<int, string>
     */
    public static function brandNames(Shop $shop): array
    {
        $names = Arr::get($shop->settings ?? [], self::BRAND_NAMES_SETTING);

        if (is_array($names) && $names !== []) {
            return array_values($names);
        }

        return collect([$shop->name, $shop->website?->name])->filter()->unique(fn (string $name) => mb_strtolower($name))->values()->all();
    }

    public function handle(SeoAiPrompt $prompt, array $result): SeoAiAnswer
    {
        $shop      = $prompt->shop;
        $date      = Carbon::parse(Arr::get($result, 'datetime') ?? now())->toDateString();
        $markdown  = (string) Arr::get($result, 'markdown');
        $text      = preg_replace('/!\[[^\]]*]\([^)]*\)/u', '', $markdown) ?? $markdown;
        $entries   = $this->listEntries($text);
        $sources   = $this->sources($result);
        $ourDomain = (string) $shop->website?->domain;
        $ourNames  = self::brandNames($shop);

        $competitors = $shop->seoCompetitors->map(fn (SeoCompetitor $competitor) => [
            'competitor_id'  => $competitor->id,
            'is_mentioned'   => $this->isNamed($text, $competitor->domain, array_filter([$competitor->label])),
            'is_cited'       => $sources->contains(fn (array $source) => StoreSerpResult::isDomain($source['domain'], $competitor->domain)),
            'brand_position' => $this->place($entries, $competitor->domain, array_filter([$competitor->label])),
        ])->values()->all();

        return DB::transaction(function () use ($prompt, $shop, $date, $result, $markdown, $text, $entries, $sources, $ourDomain, $ourNames, $competitors) {
            $answer = SeoAiAnswer::updateOrCreate(
                ['prompt_id' => $prompt->id, 'platform' => SeoAiAnswer::CHAT_GPT, 'date' => $date],
                [
                    'model'           => Arr::get($result, 'model'),
                    'answer'          => $markdown ?: null,
                    'is_mentioned'    => $ourDomain !== '' && $this->isNamed($text, $ourDomain, $ourNames),
                    'brand_position'  => $ourDomain !== '' ? $this->place($entries, $ourDomain, $ourNames) : null,
                    'is_cited'        => $ourDomain !== '' && $sources->contains(fn (array $source) => StoreSerpResult::isDomain($source['domain'], $ourDomain)),
                    'brands'          => array_column($entries, 'title'),
                    'competitors'     => $competitors,
                    'fan_out_queries' => Arr::get($result, 'fan_out_queries') ?: null,
                    'check_url'       => Arr::get($result, 'check_url'),
                ]
            );

            $answer->citations()->delete();

            $now = now();
            SeoAiCitation::insert($sources->values()->map(function (array $source, int $index) use ($answer, $shop, $now) {
                $website = $this->ourWebsite($source['domain']);

                return [
                    'answer_id'     => $answer->id,
                    'position'      => $index + 1,
                    'url'           => $source['url'],
                    'domain'        => $source['domain'],
                    'title'         => $source['title'],
                    'competitor_id' => $shop->seoCompetitors->first(fn (SeoCompetitor $competitor) => StoreSerpResult::isDomain($source['domain'], $competitor->domain))?->id,
                    'website_id'    => $website?->id,
                    'webpage_id'    => $website ? $this->matchWebpageId($source['url'], $this->webpageIds($website)) : null,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ];
            })->all());

            $prompt->update([
                'queued_at'   => null,
                'last_run_at' => $date,
            ]);

            return $answer;
        });
    }

    /**
     * The sources the answer cites, once each, in the order it gives them.
     *
     * @return Collection<int, array{url: string, domain: string, title: string|null}>
     */
    private function sources(array $result): Collection
    {
        $sources = collect(Arr::get($result, 'sources') ?? []);

        if ($sources->isEmpty()) {
            $sources = collect(Arr::get($result, 'items') ?? [])->flatMap(fn (array $item) => Arr::get($item, 'sources') ?? []);
        }

        return $sources
            ->filter(fn ($source) => is_array($source) && Arr::get($source, 'url'))
            ->map(fn (array $source) => [
                'url'    => (string) $source['url'],
                'domain' => StoreSerpResult::normaliseDomain(Arr::get($source, 'domain') ?: $source['url']),
                'title'  => Arr::get($source, 'title'),
            ])
            ->unique('url')
            ->values();
    }

    /**
     * The entries of the list the answer is built on: the kind of line (a heading level, numbered
     * items, bullets that start in bold, or lines in bold on their own) it uses most, at least twice,
     * in order.
     *
     * @return array<int, array{title: string, line: string}>
     */
    private function listEntries(string $text): array
    {
        $kinds = [];

        foreach (preg_split('/\R/u', $text) as $line) {
            if (preg_match('/^(#{2,6})\s+(.+)$/u', $line, $match)) {
                $kinds[$match[1]][] = $match[2];
            } elseif (preg_match('/^\s{0,3}\d+\\\\?[.)]\s+(.+)$/u', $line, $match)) {
                $kinds['numbered'][] = $match[1];
            } elseif (preg_match('/^\s{0,3}[-*+]\s+(\*\*.+)$/u', $line, $match)) {
                $kinds['bullet'][] = $match[1];
            } elseif (preg_match('/^\s{0,3}(\*\*[^*].*\*\*)\s*$/u', $line, $match)) {
                $kinds['bold'][] = $match[1];
            }
        }

        $lines = collect($kinds)->filter(fn (array $lines) => count($lines) >= 2)->sortByDesc(fn (array $lines) => count($lines))->first() ?? [];

        return array_map(fn (string $line) => ['title' => $this->entryTitle($line), 'line' => $line], $lines);
    }

    private function entryTitle(string $line): string
    {
        $title = preg_replace('/\[([^\]]*)]\([^)]*\)/u', '$1', $line);
        $title = preg_replace('/^\d+\\\\?[.)]\s*/u', '', str_replace(['**', '__', '`'], '', $title));
        $title = preg_split('/\s[–—-]\s|:\s/u', trim($title))[0];

        return mb_substr(trim($title), 0, 120);
    }

    /**
     * The place of the brand in the answer's list, from 1.
     *
     * @param  array<int, array{title: string, line: string}>  $entries
     * @param  array<int, string>  $names
     */
    private function place(array $entries, string $domain, array $names): ?int
    {
        foreach ($entries as $index => $entry) {
            if ($this->isNamed($entry['line'], $domain, $names)) {
                return $index + 1;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $names
     */
    private function isNamed(string $text, string $domain, array $names): bool
    {
        if ($text === '') {
            return false;
        }

        if (preg_match('/(?<![\p{L}\p{N}.-])(www\.)?'.preg_quote(StoreSerpResult::normaliseDomain($domain), '/').'(?![\p{L}\p{N}-])/iu', $text)) {
            return true;
        }

        return collect($names)->contains(fn (string $name) => $this->nameMatches($name, $text));
    }

    /**
     * A name matches its words in order, with or without spaces, hyphens or dots between them, so
     * "AW Dropship" also finds "AW-Dropship" and "AWDropship".
     */
    private function nameMatches(string $name, string $text): bool
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', $name, -1, PREG_SPLIT_NO_EMPTY);

        if (!$words || $text === '') {
            return false;
        }

        $pattern = implode('[\s\-.&+]*', array_map(fn (string $word) => preg_quote($word, '/'), $words));

        return (bool) preg_match('/(?<![\p{L}\p{N}])'.$pattern.'(?![\p{L}\p{N}])/iu', $text);
    }

    private function ourWebsite(string $domain): ?Website
    {
        $this->websitesByDomain ??= Website::where('state', WebsiteStateEnum::LIVE)
            ->get(['id', 'domain'])
            ->keyBy(fn (Website $website) => StoreSerpResult::normaliseDomain($website->domain))
            ->all();

        foreach ($this->websitesByDomain as $websiteDomain => $website) {
            if (StoreSerpResult::isDomain($domain, $websiteDomain)) {
                return $website;
            }
        }

        return null;
    }

    /**
     * @return array<string, int>
     */
    private function webpageIds(Website $website): array
    {
        return $this->webpageIdsByPathPerWebsite[$website->id] ??= $this->webpageIdsByPath($website);
    }
}
