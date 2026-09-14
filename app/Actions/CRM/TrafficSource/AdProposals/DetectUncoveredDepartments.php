<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\TrafficSource\AdProposals;

use App\Enums\CRM\TrafficSource\AdProposalTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\TrafficSourceCampaign;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Whole departments the shop sells and never advertises.
 *
 * Departments rather than families because there are thirteen of the former and eight hundred of the
 * latter: a department is a thing a campaign could plausibly be built around, a family is a shelf.
 *
 * Both coverage and demand are judged on the words in the department's PRODUCT names, not on the
 * department's own name. Customers do not shop in categories: on a real account "Musical Instruments"
 * appeared in seven site searches and its products' words, singing and bowl and drum, appeared in two
 * hundred and sixty four. Matching on the category name missed the department entirely, and would
 * equally have called a department uncovered while the account advertised it under product words.
 *
 * Yield is naturally low and that is the point: on a live account twelve of thirteen departments were
 * covered, and the one that was not had visitors searching the site for singing bowls.
 */
class DetectUncoveredDepartments
{
    use AsAction;

    private const int DAYS = 90;

    /** One visitor searching for it is an accident; a handful is a pattern worth a campaign. */
    private const int MIN_SESSIONS = 3;

    private const int MIN_COVERAGE_WORDS = 2;

    /**
     * @param Collection<string, TrafficSourceCampaign> $campaigns
     * @return array<int, array>
     */
    public function handle(Shop $shop, Collection $campaigns): array
    {
        $covered     = $this->accountVocabulary($campaigns);
        $departments = $this->departments($shop);
        $candidates  = [];

        /* Two word sets, for two different questions.
           Coverage asks "does the account mention this department at all", and wants every word it
           has: strip the shared ones and Candles & Holders loses `candle` and looks unadvertised
           beside an ad group called Candle Dropship.
           Seeding asks "what would this campaign bid on", and wants only the words unique to the
           department: `stick` belongs to incense as much as to drums, and left in it seeded a
           musical instruments campaign with incense searches. */
        $distinctive = $this->distinctiveVocabularies($shop, $departments);

        foreach ($departments as $department) {
            $words = $this->vocabularyOf($shop, $department);

            if ($words === [] || $this->isCovered($words, $covered)) {
                continue;
            }

            $demand = $this->demandFor($shop, $distinctive[$department->id] ?? $words);

            /* Without demand there is nothing to say beyond "you sell this", which is not a reason to
               spend money. The searches are what turns a gap into a suggestion. */
            if ($demand['sessions'] < self::MIN_SESSIONS) {
                continue;
            }

            $candidates[] = [
                'type'        => AdProposalTypeEnum::NEW_CAMPAIGN,
                'campaign_id' => null,
                'fingerprint' => AdProposalFingerprint::run(AdProposalTypeEnum::NEW_CAMPAIGN, [(string) $department->id]),
                'subject'     => $department->name,
                'payload'     => [
                    'department_id' => $department->id,
                    'name'          => $department->name,
                    'keywords'      => $demand['terms'],
                ],
                'evidence' => [
                    'term'          => $department->name,
                    'department'    => $department->name,
                    'products'      => (int) $department->products,
                    'sessions'      => $demand['sessions'],
                    'searches'      => $demand['searches'],
                    'example_terms' => $demand['terms'],
                    'days'          => self::DAYS,
                    'rule'          => 'you sell this and no campaign, ad group or keyword mentions it',
                ],
                'amount' => 0,
                'rank'   => $demand['sessions'],
            ];
        }

        return $candidates;
    }

    /**
     * @return Collection<int, object>
     */
    private function departments(Shop $shop): Collection
    {
        return DB::table('product_categories as pc')
            ->where('pc.shop_id', $shop->id)
            ->where('pc.type', 'department')
            ->whereNull('pc.deleted_at')
            ->leftJoin('products as p', function ($join) {
                $join->on('p.department_id', '=', 'pc.id')->whereNull('p.deleted_at');
            })
            ->groupBy('pc.id', 'pc.name')
            ->havingRaw('count(p.id) > 0')
            ->get(['pc.id', 'pc.name', DB::raw('count(p.id) as products')]);
    }

    /**
     * Every word the account already uses, across campaign names, ad group names and keywords.
     */
    private function accountVocabulary(Collection $campaigns): string
    {
        return $campaigns
            ->flatMap(function (TrafficSourceCampaign $campaign) {
                $words = collect([$campaign->name]);

                foreach (Arr::get($campaign->data, 'ad_groups', []) as $group) {
                    $words->push(Arr::get($group, 'name'));

                    foreach (Arr::get($group, 'keywords', []) as $keyword) {
                        $words->push(Arr::get($keyword, 'text'));
                    }
                }

                return $words;
            })
            ->filter()
            ->map(fn ($word) => mb_strtolower($word))
            ->implode(' ');
    }

    /**
     * Two words, not one.
     *
     * A single match is usually a coincidence between departments that share a word. On a live account
     * "Musical Instruments" was marked advertised on the strength of `stick`, which the account carries
     * because it sells incense sticks. Every department that really was advertised matched between
     * four and eight words, so the line sits comfortably between the two.
     *
     * @param array<int, string> $words
     */
    private function isCovered(array $words, string $vocabulary): bool
    {
        $matches = 0;

        foreach ($words as $word) {
            if (str_contains($vocabulary, $word) && ++$matches >= self::MIN_COVERAGE_WORDS) {
                return true;
            }
        }

        return false;
    }

    /**
     * On-site searches that touch this department's words, which double as the starting keywords for
     * the campaign the marketer would build.
     *
     * @param array<int, string> $words
     * @return array{sessions: int, searches: int, terms: array<int, string>}
     */
    private function demandFor(Shop $shop, array $words): array
    {
        $query = DB::table('website_search_logs')
            ->where('shop_id', $shop->id)
            ->where('created_at', '>=', now()->subDays(self::DAYS))
            ->where('results_count', '>', 0)
            ->where(function ($where) use ($words) {
                foreach ($words as $word) {
                    /* Whole words. A LIKE on `%wide%` pulled `extra-wide garden kneeler` into a
                       proposal about drums. */
                    $where->orWhereRaw('lower(query) ~ ?', ['\y'.preg_quote($word, '/').'\y']);
                }
            });

        $rows = (clone $query)
            ->groupByRaw('lower(trim(query))')
            ->orderByRaw('count(distinct session_id) desc')
            ->limit(10)
            ->get([
                DB::raw('lower(trim(query)) as term'),
                DB::raw('count(*) as searches'),
                DB::raw('count(distinct session_id) as sessions'),
            ]);

        return [
            'sessions' => (int) $rows->sum('sessions'),
            'searches' => (int) $rows->sum('searches'),
            'terms'    => $rows->pluck('term')->values()->all(),
        ];
    }

    /**
     * Each department's words with the ones it shares with another department removed.
     *
     * @param Collection<int, object> $departments
     * @return array<int, array<int, string>>
     */
    private function distinctiveVocabularies(Shop $shop, Collection $departments): array
    {
        $byDepartment = [];
        $seenIn       = [];

        foreach ($departments as $department) {
            $words                          = $this->vocabularyOf($shop, $department);
            $byDepartment[$department->id]  = $words;

            foreach ($words as $word) {
                $seenIn[$word] = ($seenIn[$word] ?? 0) + 1;
            }
        }

        foreach ($byDepartment as $id => $words) {
            $byDepartment[$id] = array_values(array_filter($words, fn (string $word) => ($seenIn[$word] ?? 0) === 1));
        }

        return $byDepartment;
    }

    /**
     * The words this department is actually shopped for: the commonest significant words across its
     * product names, plus the department's own name for the cases where they agree.
     *
     * @return array<int, string>
     */
    private function vocabularyOf(Shop $shop, object $department): array
    {
        $names = DB::table('products')
            ->where('department_id', $department->id)
            ->whereNull('deleted_at')
            ->limit(400)
            ->pluck('name');

        $counted = [];

        foreach ($names as $name) {
            foreach ($this->words((string) $name) as $word) {
                $counted[$word] = ($counted[$word] ?? 0) + 1;
            }
        }

        arsort($counted);

        /* The commonest few only. A long tail of words that appear once apiece matches half the site
           search log and would call every department covered. */
        return collect(array_keys(array_slice($counted, 0, 8)))
            ->merge($this->words($department->name))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function words(string $name): array
    {
        return collect(preg_split('/[^a-z0-9]+/i', mb_strtolower($name)))
            /* Sizes, colours and packaging words appear across every department and match nothing
               useful in a search log. */
            ->filter(fn (string $word) => mb_strlen($word) > 3 && !in_array($word, [
                'and', 'the', 'with', 'set', 'sets', 'pack', 'assorted', 'large', 'small', 'medium',
                'black', 'white', 'blue', 'green', 'multi', 'coloured', 'colored', 'natural', 'design',
                'designs', 'style', 'styles', 'each', 'piece', 'pieces',
            ], true))
            ->filter(fn (string $word) => !preg_match('/\d/', $word))
            ->map(fn (string $word) => rtrim($word, 's'))
            ->unique()
            ->values()
            ->all();
    }
}
