<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\TrafficSource\AdProposals;

use App\Actions\Helpers\AI\AskToAi;
use App\Enums\CRM\TrafficSource\AdProposalTypeEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * The one place a language model is consulted, and the only question it is asked: does this phrase
 * read like somebody who would buy from this shop?
 *
 * It is asked nothing else. It does not find the candidates, it does not compute a figure, and no
 * number it produces is stored or shown. It sees a shortlist and the shop's trade, and answers keep
 * or drop plus one sentence a marketer will read.
 *
 * If it is unavailable, over budget or answers with nonsense, every candidate is kept and shown
 * without a sentence. The arithmetic behind them is true either way, and a queue without explanations
 * is far better than no queue: the model improves this feature, it does not gate it.
 *
 * One call per proposal type, not one for all of them. Asking in a single call meant "keep" meant
 * "yes, exclude this" for one type and "yes, add this" for another, in the same list, and the answers
 * showed it: the sentences came back interchangeable, the same eleven words about aligning with
 * customer interests repeated down the page.
 */
class JudgeAdProposalCandidates
{
    use AsAction;

    /**
     * Above this share of identical sentences, the prose is treated as filler and thrown away while
     * the candidates are kept. A model that has written the same sentence for most of a list has
     * stopped reading it, and twenty copies of one sentence teach a marketer nothing while implying
     * something was considered.
     */
    private const float BOILERPLATE_SHARE = 0.5;

    /**
     * @param array<int, array> $candidates
     * @return array<int, array>
     */
    public function handle(Shop $shop, array $candidates): array
    {
        if ($candidates === []) {
            return [];
        }

        $sells = $this->whatTheShopSells($shop);
        $kept  = [];

        foreach (collect($candidates)->groupBy(fn (array $c) => $c['type']->value) as $type => $group) {
            $kept = array_merge($kept, $this->judgeGroup(AdProposalTypeEnum::from($type), $group->values()->all(), $shop, $sells));
        }

        return $kept;
    }

    /**
     * @param array<int, array> $candidates
     * @return array<int, array>
     */
    private function judgeGroup(AdProposalTypeEnum $type, array $candidates, Shop $shop, string $sells): array
    {
        try {
            $verdicts = $this->parse(AskToAi::run($this->prompt($type, $candidates, $shop, $sells)));
        } catch (Throwable $exception) {
            Log::warning('Ad proposal judging failed, keeping every candidate unexplained', [
                'shop'  => $shop->slug,
                'type'  => $type->value,
                'error' => $exception->getMessage(),
            ]);

            return $candidates;
        }

        if ($verdicts === []) {
            return $candidates;
        }

        $useRationale = !$this->isBoilerplate($verdicts);
        $kept         = [];

        foreach ($candidates as $index => $candidate) {
            $verdict = $verdicts[$index] ?? null;

            /* Silence is not a rejection. A model that answers about nine of eleven candidates has
               not judged the other two, and dropping them would lose real findings to a truncated
               reply. */
            if ($verdict !== null && $verdict['keep'] === false) {
                continue;
            }

            $candidate['rationale'] = $useRationale ? ($verdict['why'] ?? null) : null;
            $kept[]                 = $candidate;
        }

        return $kept;
    }

    /**
     * @param array<int, array{keep: bool, why: string|null}> $verdicts
     */
    private function isBoilerplate(array $verdicts): bool
    {
        $sentences = collect($verdicts)->pluck('why')->filter();

        if ($sentences->count() < 3) {
            return false;
        }

        $commonest = $sentences
            ->map(fn (string $why) => mb_strtolower(preg_replace('/[^a-z ]/i', '', $why)))
            ->countBy()
            ->sortDesc()
            ->first();

        return $commonest / $sentences->count() >= self::BOILERPLATE_SHARE;
    }

    /**
     * @param array<int, array> $candidates
     */
    private function prompt(AdProposalTypeEnum $type, array $candidates, Shop $shop, string $sells): string
    {
        $lines = [];

        foreach ($candidates as $index => $candidate) {
            $lines[] = sprintf('%d. "%s" (%s)', $index, $candidate['subject'], $this->facts($candidate));
        }

        $list = implode("\n", $lines);

        return <<<PROMPT
        You are helping the marketing team at "{$shop->name}", a business that sells: {$sells}.

        {$this->task($type)}

        Rules for your reply:
        - Reply with a JSON array and nothing else.
        - One object per numbered item: [{"id": 0, "keep": true, "why": "..."}]
        - "why" is one short sentence for a marketer, and must be SPECIFIC to that phrase. Name the
          kind of product it matches, or name what is wrong with it. A sentence that would read just
          as well against any other phrase in the list is a wrong answer. Never write anything like
          "this phrase aligns with potential customer interests".
        - "why" must contain no numbers. The figures are shown separately and are already verified.

        Items:
        {$list}
        PROMPT;
    }

    private function task(AdProposalTypeEnum $type): string
    {
        return match ($type) {
            /* The bar for excluding is deliberately high. These phrases have cost money without a
               recorded sale, but a recorded sale is not the only reason a search is worth buying, and
               an account whose conversion tracking is incomplete will show zero for searches that did
               sell. Excluding a phrase real customers use is far more expensive than leaving it. */
            AdProposalTypeEnum::EXCLUDE_SEARCH_TERM => 'Each phrase below is a Google search that brought clicks and recorded no sale. '
                .'Set keep to true ONLY if the phrase is clearly the wrong audience for this business, '
                .'for example someone looking for a job, a course, a competitor, or a product this business does not sell. '
                .'Set keep to false if it is a phrase a real customer of this business would plausibly type, even though it has not sold yet.',

            AdProposalTypeEnum::PAUSE_KEYWORD => 'Each phrase below is a keyword this business pays for that has recorded no sale over three months. '
                .'Set keep to true ONLY if the keyword is clearly the wrong audience for this business. '
                .'Set keep to false if it describes what this business actually sells, even though it has not sold yet.',

            AdProposalTypeEnum::ADD_SEARCH_TERM_KEYWORD => 'Each phrase below is a Google search that led to a sale, and this business does not bid on it directly. '
                .'Set keep to true if bidding on it deliberately makes sense for this business. '
                .'Set keep to false only if the phrase would attract the wrong people despite the sale.',

            AdProposalTypeEnum::ADD_DEMAND_KEYWORD => 'Each phrase below is something visitors typed into this business\'s own website search, and found products. '
                .'Set keep to true if somebody searching Google for this phrase would be a good customer for this business, and name the kind of product it matches. '
                .'Set keep to false if it is not a buying phrase: an existing customer looking up an order or an account, a brand or code only existing customers would know, '
                .'or a word too vague to attract the right person.',
        };
    }

    private function facts(array $candidate): string
    {
        $evidence = $candidate['evidence'];

        if ($candidate['type'] === AdProposalTypeEnum::ADD_DEMAND_KEYWORD) {
            return "{$evidence['sessions']} visitors searched the site for it";
        }

        return "{$evidence['clicks']} clicks, {$evidence['conversions']} recorded sales";
    }

    /**
     * The shop's trade in its own catalogue's words.
     *
     * Departments, not families: there are thirteen of the former and eight hundred of the latter, so
     * a sample of families is a biased slice of one corner of the catalogue while the departments are
     * the whole business. Sampled families cost real accuracy here. Thirty of them, taken in id order,
     * happened to be mostly essential oils, and the model then judged `tarot cards` and `crystals` a
     * poor fit for a shop whose departments include Crystals & Esoterics.
     */
    public function shopTrade(Shop $shop): string
    {
        return $this->whatTheShopSells($shop);
    }

    private function whatTheShopSells(Shop $shop): string
    {
        $departments = DB::table('product_categories')
            ->where('shop_id', $shop->id)
            ->where('type', 'department')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->pluck('name')
            ->filter();

        if ($departments->isNotEmpty()) {
            return $departments->implode(', ');
        }

        $families = DB::table('product_categories')
            ->where('shop_id', $shop->id)
            ->where('type', 'family')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->limit(40)
            ->pluck('name')
            ->filter();

        return $families->isNotEmpty() ? $families->implode(', ') : 'a range of retail products';
    }

    /**
     * @return array<int, array{keep: bool, why: string|null}>
     */
    private function parse(?string $answer): array
    {
        if (blank($answer)) {
            return [];
        }

        /* Models wrap JSON in prose or a fenced block however firmly they are told not to, so the
           array is taken from wherever it sits rather than the whole reply being trusted. */
        if (!preg_match('/\[.*]/s', $answer, $matches)) {
            return [];
        }

        $decoded = json_decode($matches[0], true);

        if (!is_array($decoded)) {
            return [];
        }

        $judged = [];

        foreach ($decoded as $verdict) {
            if (!is_array($verdict) || !isset($verdict['id'])) {
                continue;
            }

            $why = trim((string) ($verdict['why'] ?? ''));

            $judged[(int) $verdict['id']] = [
                'keep' => (bool) ($verdict['keep'] ?? true),
                'why'  => $why !== '' ? $why : null,
            ];
        }

        return $judged;
    }
}
