<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\Competitor;

use App\Actions\Helpers\AI\AskJev;
use App\Enums\Masters\Competitor\MasterAssetCompetitorProductStatusEnum;
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
 * asked again. The pack size is read from the competitor's product name.
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

    public const string INSTRUCTIONS = 'We sell our_product to retailers. A competitor sells the products listed. Pick the one that is the same item as ours (same kind, design, brand, size and material; the pack size may differ) or, failing that, one a retailer could stock instead of ours, or none. A different brand, size or scent is not the same item. weight_vs_ours close to 1 suggests the same size; far from 1 a different size, unless one of them is weighed as a whole set.';

    /**
     * @param  array<int, int>  $masterAssetIds
     */
    public function handle(Competitor $competitor, array $masterAssetIds): int
    {
        $matches = 0;

        foreach (MasterAsset::whereIn('id', $masterAssetIds)->get() as $masterAsset) {
            $candidates = $this->candidates($competitor, $masterAsset)->values();
            if ($candidates->isEmpty()) {
                continue;
            }

            $answer = AskJev::make()->choice(static::state($masterAsset, $candidates), self::INSTRUCTIONS, static::options($candidates));

            if (!$answer) {
                continue;
            }

            $decision = static::decide($answer, $candidates->count());

            foreach ($candidates as $index => $candidate) {
                $picked = $decision && $decision['index'] === $index;

                $candidate->update(['units' => static::packUnits($candidate->name, $masterAsset->name)]);

                MasterAssetCompetitorProduct::create([
                    'master_shop_id'        => $masterAsset->master_shop_id,
                    'master_asset_id'       => $masterAsset->id,
                    'competitor_product_id' => $candidate->id,
                    'is_same_item'          => $picked && $decision['same'],
                    'confidence'            => $picked ? $decision['confidence'] : null,
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
    public static function state(MasterAsset $masterAsset, Collection $candidates, bool $detailed = true): array
    {
        $dimensions = $masterAsset->marketing_dimensions ?? [];

        $ours = ['name' => $masterAsset->name, 'units_in_our_pack' => (float) $masterAsset->units];
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
     * "Set of 12 Satya incense sticks" holds 12, "Carton of 6 packets" 6, anything else 1. When our
     * own name has a "set of" too, the set is the item ("Set of 4 coasters" against ours), so 1.
     */
    public static function packUnits(string $name, string $ourName = ''): float
    {
        if (static::countInName($ourName) > 1) {
            return 1.0;
        }

        return (float) max(static::countInName($name), 1);
    }

    private static function countInName(string $name): int
    {
        foreach ([
            '/\b(?:set|pack|box|bag|bundle|carton|case|tub|tray|lot)\s+of\s+(\d+)\b/i',
            '/\b(\d+)\s*(?:pcs|pieces|packets|packs|pack|x)\b/i',
            '/\bx\s?(\d+)\b/i',
        ] as $pattern) {
            if (preg_match($pattern, $name, $matches) && (int) $matches[1] > 1) {
                return (int) $matches[1];
            }
        }

        return 1;
    }

    /**
     * @return Collection<int, CompetitorProduct>
     */
    private function candidates(Competitor $competitor, MasterAsset $masterAsset): Collection
    {
        return CompetitorProduct::where('competitor_id', $competitor->id)
            ->whereRaw('name % ?', [$masterAsset->name])
            ->whereRaw('similarity(name, ?) >= ?', [$masterAsset->name, self::MIN_SIMILARITY])
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
                ->from('master_asset_competitor_products')
                ->whereColumn('master_asset_competitor_products.competitor_product_id', 'competitor_products.id')
                ->where('master_asset_competitor_products.master_asset_id', $masterAsset->id))
            ->orderByRaw('similarity(name, ?) desc', [$masterAsset->name])
            ->limit(self::CANDIDATES)
            ->get();
    }

    /**
     * Every product of the competitor's master shop with a name close to one in the feed and not
     * looked at yet, in chunks of 50.
     */
    public function queue(Competitor $competitor, bool $sync = false): int
    {
        $ids = DB::table('master_assets')
            ->where('master_shop_id', $competitor->master_shop_id)
            ->where('status', true)
            ->where('is_main', true)
            ->whereExists(fn ($query) => $query->select(DB::raw(1))
                ->from('competitor_products')
                ->where('competitor_products.competitor_id', $competitor->id)
                ->whereRaw('competitor_products.name % master_assets.name')
                ->whereRaw('similarity(competitor_products.name, master_assets.name) >= ?', [self::MIN_SIMILARITY])
                ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
                    ->from('master_asset_competitor_products')
                    ->whereColumn('master_asset_competitor_products.competitor_product_id', 'competitor_products.id')
                    ->whereColumn('master_asset_competitor_products.master_asset_id', 'master_assets.id')))
            ->pluck('id');

        foreach ($ids->chunk(self::CHUNK) as $chunk) {
            $sync ? $this->handle($competitor, $chunk->values()->all()) : static::dispatch($competitor, $chunk->values()->all());
        }

        return $ids->count();
    }
}
