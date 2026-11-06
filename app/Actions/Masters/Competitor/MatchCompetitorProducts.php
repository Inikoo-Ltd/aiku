<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\Competitor;

use App\Actions\Helpers\AI\AskJev;
use App\Enums\Masters\Competitor\MasterAssetCompetitorProductStatusEnum;
use App\Models\Catalogue\Shop;
use App\Models\Masters\Competitor;
use App\Models\Masters\CompetitorProduct;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterAssetCompetitorProduct;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Finds our products in a competitor's feed. Barcodes differ between suppliers, so the database
 * short-lists the closest names and Jev picks the one that is the same item, a similar one or
 * none, with a calibrated probability. A same-item pick Jev is sure of is confirmed straight
 * away; the rest wait for staff. Candidates not picked are kept as rejected so they are never
 * asked again. The pack size is read from the competitor's product name, or from the start of its
 * description when the name gives none. A candidate whose volume or weight in the name differs from
 * ours (5ml against our 10ml) is rejected without asking Jev.
 */
class MatchCompetitorProducts
{
    use AsAction;

    public string $jobQueue = 'long-low-priority';

    public int $jobTimeout = 1800;

    public const float MIN_SIMILARITY = 0.4;

    public const float AUTO_CONFIRM = 0.7;

    public const int CANDIDATES = 4;

    private const int CHUNK = 50;

    private const array KEYS = ['a', 'b', 'c', 'd'];

    private const string SIZES = '/(?<![\d.,])(\d+(?:\.\d+)?)(\s*(?:ml|cl|ltrs?|litres?|liters?|kg|kilos?|grams?|gms?|gr|g)|l)(?![a-z])/i';

    private const string DIMENSIONS = '/\d+(?:\.\d+)?(?:\s*(?:mm|cm|in|″|”|"))?(?:\s*[x×]\s*\d+(?:\.\d+)?(?:\s*(?:mm|cm|in|″|”|"))?)*\s*[x×]\s*\d+(?:\.\d+)?\s*(?:mm|cm|m|in|inch|inches|″|”|")(?![a-z])/iu';

    private const string NOT_A_SIZE = '(?![\d.]|\s*(?:ml|cl|ltrs?|litres?|liters?|l|kg|kilos?|grams?|gms?|gr|g|mm|cm)\b)';

    private const array NAME_COUNTS = [
        '/\b(?:set|pack|box|bag|bundle|carton|case|tub|tray|lot|outer)\s+of\s+(\d+)'.self::NOT_A_SIZE.'/i',
        '/\b(\d+)\s*(?:pcs?|pieces?|units?|packets?|packs?|pk(?:ts?|s)?|boxes|bxs?|x)\b/i',
        '/\bx\s?(\d+)\b/i',
    ];

    private const array DESCRIPTION_COUNTS = [
        '/\b(\d+)\s*x\s*packs?\b/i',
        '/\b(\d+)\s*pk(?:ts?|s)\b/i',
        '/\bbox\s+of\s+(\d+)\s+packs?\b/i',
    ];

    public const string INSTRUCTIONS = 'We sell our_product to retailers. A competitor sells the products listed. Pick the one that is the same item as ours (same kind, design, brand, size and material; the pack size may differ) or, failing that, one a retailer could stock instead of ours, or none. A different brand, size or scent is not the same item. weight_vs_ours close to 1 suggests the same size; far from 1 a different size, unless one of them is weighed as a whole set.';

    /**
     * @param  array<int, int>  $masterAssetIds
     */
    public function handle(Competitor $competitor, array $masterAssetIds): int
    {
        $matches    = 0;
        $localNames = static::localNames($competitor, $masterAssetIds);

        foreach (MasterAsset::whereIn('id', $masterAssetIds)->get() as $masterAsset) {
            $localName  = $localNames[$masterAsset->id] ?? $masterAsset->name;
            $shortlist  = $this->candidates($competitor, $masterAsset, $localName);
            $otherSizes = $shortlist->filter(fn (CompetitorProduct $candidate) => static::sizeDiffers($masterAsset->name, $candidate->name));
            $candidates = $shortlist->diffKeys($otherSizes)->take(self::CANDIDATES)->values();

            $decision = null;
            if ($candidates->isNotEmpty()) {
                $answer = AskJev::make()->choice(static::state($masterAsset, $candidates, nameInTheirLanguage: $localName), self::INSTRUCTIONS, static::options($candidates));

                if (!$answer) {
                    continue;
                }

                $decision = static::decide($answer, $candidates->count());
            }

            foreach ($candidates->concat($otherSizes)->values() as $index => $candidate) {
                $picked = $decision && $decision['index'] === $index;

                $candidate->update(['units' => static::packUnits($candidate->name, $candidate->data['description'] ?? '')]);

                MasterAssetCompetitorProduct::create([
                    'master_shop_id'        => $masterAsset->master_shop_id,
                    'master_asset_id'       => $masterAsset->id,
                    'competitor_product_id' => $candidate->id,
                    'is_same_item'          => $picked && $decision['same'],
                    'confidence'            => $picked ? $decision['confidence'] : null,
                    'competitor_units'      => static::isSetItem($masterAsset->name, (float) $masterAsset->units) ? 1 : null,
                    'status'                => match (true) {
                        !$picked                                                        => MasterAssetCompetitorProductStatusEnum::REJECTED,
                        $decision['same'] && $decision['confidence'] >= self::AUTO_CONFIRM => MasterAssetCompetitorProductStatusEnum::CONFIRMED,
                        default                                                         => MasterAssetCompetitorProductStatusEnum::SUGGESTED,
                    },
                ]);

                $matches += $picked ? 1 : 0;
            }
        }

        return $matches;
    }

    /**
     * What Jev sees: both sides' name, category hints (our family, tariff code, material), size,
     * weight and the start of the description, so brand, size and set-or-single can be told apart.
     *
     * @param  Collection<int, CompetitorProduct>  $candidates
     */
    public static function state(MasterAsset $masterAsset, Collection $candidates, bool $detailed = true, ?string $nameInTheirLanguage = null): array
    {
        $dimensions = $masterAsset->marketing_dimensions ?? [];

        $ours = ['name' => $masterAsset->name]
            + ($nameInTheirLanguage && $nameInTheirLanguage !== $masterAsset->name ? ['name_in_their_language' => $nameInTheirLanguage] : [])
            + ['units_in_our_pack' => (float) $masterAsset->units];
        if ($detailed) {
            $ours += array_filter([
                'family'      => $masterAsset->masterFamily?->name,
                'size'        => implode(' x ', array_filter([$dimensions['l'] ?? null, $dimensions['w'] ?? null, $dimensions['h'] ?? null])).($dimensions ? ' '.($dimensions['units'] ?? '') : ''),
                'weight_g'    => $masterAsset->marketing_weight,
                'tariff_code' => $masterAsset->tariff_code,
                'description' => mb_substr(trim(strip_tags((string) $masterAsset->description)), 0, 400),
            ]);
        }

        return [
            'our_product' => $ours,
            'competitor'  => $candidates->values()->map(fn (CompetitorProduct $candidate, int $index) => ['option' => self::KEYS[$index], 'name' => $candidate->name] + ($detailed ? array_filter([
                'weight_vs_ours' => static::weightRatio($candidate->data['weight_g'] ?? null, $masterAsset->marketing_weight),
            ]) + $candidate->data : []))->all(),
        ];
    }

    /**
     * Their weight over ours, e.g. 0.98: close to 1 points to the same size, unless one side weighs a whole set.
     */
    public static function weightRatio(mixed $theirs, mixed $ours): ?float
    {
        return (float) $theirs > 0 && (float) $ours > 0 ? round((float) $theirs / (float) $ours, 2) : null;
    }

    /**
     * @param  Collection<int, CompetitorProduct>  $candidates
     * @return array<string, string>
     */
    public static function options(Collection $candidates): array
    {
        $options = [];
        foreach ($candidates->values() as $index => $candidate) {
            $name = mb_substr($candidate->name, 0, 200);
            $options[self::KEYS[$index].'_same']    = "{$name}: the same item";
            $options[self::KEYS[$index].'_similar'] = "{$name}: not the same, but similar";
        }

        return $options + ['none' => 'None of them'];
    }

    /**
     * @param  array{choice: string, probabilities?: array<string, float>}  $answer
     * @return array{index: int, same: bool, confidence: float}|null null when Jev picked none
     */
    public static function decide(array $answer, int $numberCandidates): ?array
    {
        if (!preg_match('/^([a-d])_(same|similar)$/', $answer['choice'], $matches)) {
            return null;
        }

        $index = array_search($matches[1], self::KEYS, true);
        if ($index === false || $index >= $numberCandidates) {
            return null;
        }

        return [
            'index'      => $index,
            'same'       => $matches[2] === 'same',
            'confidence' => round((float) ($answer['probabilities'][$answer['choice']] ?? $answer['confidence'] ?? 0), 4),
        ];
    }

    /**
     * What their pack holds, from what they wrote: "Set of 12 Satya incense sticks" 12, "Incense 15gm x 12pkts" 12,
     * "Carton of 6 packets" 6, anything else 1. A name with no count falls back to the start of the description
     * ("Satya Good Vibes Incense – 12 x Packs 15g ...").
     */
    public static function packUnits(string $name, string $description = ''): float
    {
        $count = static::countInName($name);

        return (float) ($count > 1 ? $count : static::countInDescription($description));
    }

    /**
     * Our product is a set sold as one item ("Set of 4 Square Coasters", units 1), so their "Set of 4" is compared as 1.
     * When our own units already count the pack (Box of 12 cones, units 12), per unit holds on both sides.
     */
    public static function isSetItem(string $ourName, float $ourUnits): bool
    {
        return $ourUnits <= 1 && static::countInName($ourName) > 1;
    }

    /**
     * Volumes in ml and weights in g written in a name: "Rose oil 1L" gives ['ml' => [1000]],
     * "Incense 15gm x 12" ['g' => [15]].
     *
     * @return array<string, array<int, float>>
     */
    public static function sizesInName(string $name): array
    {
        preg_match_all(self::SIZES, $name, $matches, PREG_SET_ORDER);

        $sizes = [];
        foreach ($matches as [, $number, $unit]) {
            [$dimension, $factor] = match (strtolower(trim($unit))) {
                'ml'                                   => ['ml', 1],
                'cl'                                   => ['ml', 10],
                'g', 'gm', 'gms', 'gr', 'gram', 'grams' => ['g', 1],
                'kg', 'kilo', 'kilos'                  => ['g', 1000],
                default                                => ['ml', 1000],
            };
            $sizes[$dimension][] = (float) $number * $factor;
        }

        return $sizes;
    }

    /**
     * True when both names give a volume, or both a weight, and none of theirs is within 5% of ours.
     * 10ml against 10g, or a name without a size, is left to Jev.
     */
    public static function sizeDiffers(string $ourName, string $theirName): bool
    {
        $theirs = static::sizesInName($theirName);
        foreach (static::sizesInName($ourName) as $dimension => $ourSizes) {
            if (isset($theirs[$dimension]) && !collect($theirs[$dimension])->crossJoin($ourSizes)->contains(fn (array $pair) => abs($pair[0] - $pair[1]) <= 0.05 * max($pair))) {
                return true;
            }
        }

        return false;
    }

    /**
     * "20 x 30cm" and 5″ x 7″ are sizes, so they are taken out before counting; the first pattern that finds
     * a number above 1 wins.
     */
    private static function countInName(string $name): int
    {
        return static::firstCount(self::NAME_COUNTS, preg_replace(self::DIMENSIONS, '', $name));
    }

    /**
     * Only the first sentence is read, and only a count of packs: "Pack of 15 sticks" or "Minimum purchase is 2 pieces"
     * say nothing about how many packs the price is for.
     */
    private static function countInDescription(string $description): int
    {
        $firstSentence = preg_split('/(?<=[.!?])\s/u', trim(strip_tags($description)))[0];

        return static::firstCount(self::DESCRIPTION_COUNTS, mb_substr($firstSentence, 0, 200));
    }

    /**
     * @param  array<int, string>  $patterns
     */
    private static function firstCount(array $patterns, string $text): int
    {
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $text, $matches);
            $count = collect($matches[1])->map(fn (string $number) => (int) $number)->first(fn (int $number) => $number > 1);

            if ($count) {
                return $count;
            }
        }

        return 1;
    }

    /**
     * @return Collection<int, CompetitorProduct>
     */
    private function candidates(Competitor $competitor, MasterAsset $masterAsset, string $localName): Collection
    {
        return CompetitorProduct::where('competitor_id', $competitor->id)
            ->whereRaw('name % ?', [$localName])
            ->whereRaw('similarity(name, ?) >= ?', [$localName, self::MIN_SIMILARITY])
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
                ->from('master_asset_competitor_products')
                ->whereColumn('master_asset_competitor_products.competitor_product_id', 'competitor_products.id')
                ->where('master_asset_competitor_products.master_asset_id', $masterAsset->id))
            ->orderByRaw('similarity(name, ?) desc', [$localName])
            ->limit(self::CANDIDATES * 3)
            ->get();
    }

    /**
     * Our name for products in the competitor's language, so a Spanish shop is compared with our Spanish names: the
     * name in our shop in that language under the same master shop, else the master's own translation, else our
     * name. A competitor in English, or whose language is not known, gets our names as they are.
     *
     * @param  array<int, int>  $masterAssetIds
     * @return array<int, string> master asset id => name
     */
    public static function localNames(Competitor $competitor, array $masterAssetIds): array
    {
        return DB::table('master_assets')
            ->whereIn('id', $masterAssetIds)
            ->selectRaw('id, '.static::localNameSql($competitor).' as local_name')
            ->pluck('local_name', 'id')
            ->all();
    }

    public static function localNameSql(Competitor $competitor): string
    {
        $language = $competitor->language;
        if (!$language || str_starts_with(mb_strtolower($language->code), 'en')) {
            return 'master_assets.name';
        }

        $code   = preg_replace('/[^a-z]/', '', strtok(mb_strtolower($language->code), '-'));
        $shopId = (int) Shop::where('master_shop_id', $competitor->master_shop_id)
            ->whereHas('language', fn ($query) => $query->whereRaw("split_part(lower(code), '-', 1) = ?", [$code]))
            ->orderBy('id')
            ->value('id');
        $inShop = $shopId ? "(select nullif(products.name, '') from products where products.master_product_id = master_assets.id and products.shop_id = $shopId order by products.id limit 1), " : '';

        return "coalesce($inShop nullif(master_assets.name_i8n->>'$code', ''), master_assets.name)";
    }

    /**
     * Every product of the competitor's master shop with a name close to one in the feed and not
     * looked at yet, in chunks of 50.
     */
    public function queue(Competitor $competitor, bool $sync = false): int
    {
        $ours = DB::table('master_assets')
            ->where('master_shop_id', $competitor->master_shop_id)
            ->where('status', true)
            ->where('is_main', true)
            ->selectRaw('master_assets.id, '.static::localNameSql($competitor).' as local_name')
            ->offset(0); // ponytail: keeps Postgres from re-running the name lookup inside every trigram probe (41 s against 8 s locally)

        $ids = DB::query()->fromSub($ours, 'ours')
            ->whereExists(fn ($query) => $query->select(DB::raw(1))
                ->from('competitor_products')
                ->where('competitor_products.competitor_id', $competitor->id)
                ->whereRaw('competitor_products.name % ours.local_name')
                ->whereRaw('similarity(competitor_products.name, ours.local_name) >= ?', [self::MIN_SIMILARITY])
                ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
                    ->from('master_asset_competitor_products')
                    ->whereColumn('master_asset_competitor_products.competitor_product_id', 'competitor_products.id')
                    ->whereColumn('master_asset_competitor_products.master_asset_id', 'ours.id')))
            ->pluck('id');

        foreach ($ids->chunk(self::CHUNK) as $chunk) {
            $sync ? $this->handle($competitor, $chunk->values()->all()) : static::dispatch($competitor, $chunk->values()->all());
        }

        return $ids->count();
    }
}
