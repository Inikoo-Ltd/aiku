<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Nov 2026 15:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\Masters\Competitor\ReadCompetitorListings;
use App\Actions\Masters\Competitor\UI\WithCompetitorPriceComparison;
use App\Enums\Masters\Competitor\MasterAssetCompetitorProductStatusEnum;
use App\Enums\SysAdmin\Authorisation\GroupPermissionsEnum;
use App\Models\Masters\Competitor;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterAssetCompetitorProduct;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

/**
 * The queue and the verdicts of the AI check on competitor matches: an assistant reads their listing, says
 * whether it is our item and how many of our units their pack holds, and that is applied at once with its
 * reason on the match. The cheap matcher only reads names, and staff have 8,000 matches waiting, so the
 * price comparison was a pack size out on a third of the confirmed ones. What a person decided is never
 * overruled: there the verdict is kept as a note. Verdicts carry no staff reviewer, so matches the check
 * turns down stay out of the staff Rejected bucket and are listed under Rejected by AI, where a tick
 * brings one back.
 */
#[Description('Checks the matches between our products and competitor products, so the price comparison can be trusted. Needs masters edit permission to save. WITHOUT match_id it returns the next matches to check (limit, default 20) and how many remain per stage: 1 confirmed matches whose price gap is out of range or was only inferred pack against pack, 2 the other confirmed matches, 3 matches suggested as the same item, 4 matches suggested as similar. Each has ours (code, name, unit: what we count as one unit, units: how many units our pack counts, trade_units: what is physically in our pack, weight_g, size, family, description, image, pack_price), theirs (competitor, sells_to, name, url, image, pack_price, units_read: the count read from their name, units_on_match, weight_g, description, minimum_order, page: the text of their listing page as our own browser read it, missing when the page could not be opened), comparison (per unit, in "currency"; difference_pct below 0 means they are cheaper; compared_pack_against_pack true means the counts did not fit and the packs were assumed equal) and staff (reviewed, units_set_by_staff). WITH match_id it saves your verdict: decision same_item, different_item or cannot_tell, a note, and units with same_item. Only matches whose listing page has been read are returned; listings_being_read says how many more are being read in the background right now and will be returned by a later call, so an empty list with listings_being_read above 0 means come back later, not that the work is done. HOW TO JUDGE: read theirs.page first, it is their listing as shown to a trade customer: the title, the pack or case size, the weight or volume, the options. Then both descriptions, weights and images. Websites usually turn assistants away, so do not rely on opening their url yourself. same_item means the same kind, brand, design, size and material: a different brand, scent, colour, size or a look-alike is different_item; a product page that is gone or shows another product is different_item. UNITS is how many of OUR units their pack holds, never their own count. Ours unit "Box", units 1, a box of 12 packets; theirs a box of 12 of the same packets: units 1, not 12. Ours units 12 (12 packets); theirs a box of 12 packets: 12; theirs one packet: 1. Ours one stick, units 1; theirs a bundle of 5: 5. Ours a box of 12 counted as 1 and theirs a single packet: 0.083. When they sell by weight and we sell by count, use the weights only if both are stated. After you save, the answer shows the comparison your units give: if their price per unit is still under 40% or over 250% of ours, read both listings again, because a real gap that large is rare and a wrong count or a different size is common; keep your verdict only when the listing proves it, and say what proves it in the note. Never guess: cannot_tell with what is missing is a good answer. A count taken from their other listings, or worked back from the price, is a guess: units are verified only when this listing or its stored description states them. The note is one or two plain sentences for our pricing staff saying what you read ("Their listing says 12 boxes of 15 g, the same box we sell as one unit"), in English, with no competitor criticism. A match a person already decided is not changed: your verdict is kept as a note for them. Saving is immediate and shown to staff on the match; there is no confirmation step.')]
class CompetitorMatchReviewTool extends Tool
{
    use WithCompetitorPriceComparison;
    use WithMcpPermissions;

    public const int DEFAULT_LIMIT = 20;

    public const int MAX_LIMIT = 50;

    public const array OUT_OF_RANGE = [-60, 150];

    public function handle(Request $request): Response
    {
        $request->validate([
            'match_id' => ['sometimes', 'integer'],
            'decision' => ['required_with:match_id', Rule::in(['same_item', 'different_item', 'cannot_tell'])],
            'units'    => ['required_if:decision,same_item', 'numeric', 'min:0.001', 'max:100000'],
            'note'     => ['required_with:match_id', 'string', 'max:500'],
            'limit'    => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
        ]);

        /** @var User|null $user */
        $user = $request->user();
        if (!$user?->group || !$this->userCan($request, GroupPermissionsEnum::MASTERS_VIEW->value)) {
            return Response::error('Permission denied.');
        }

        if (!$request->has('match_id')) {
            return Response::json($this->queue($user->group, (int) $request->integer('limit', self::DEFAULT_LIMIT)));
        }

        if (!$this->userCan($request, GroupPermissionsEnum::MASTERS_EDIT->value)) {
            return Response::error('Saving a verdict needs masters edit permission, which this user does not have. Nothing was changed.');
        }

        $match = MasterAssetCompetitorProduct::whereKey($request->integer('match_id'))
            ->whereHas('masterAsset', fn (Builder $query) => $query->where('group_id', $user->group_id))
            ->first();
        if (!$match) {
            return Response::error('No match with that id. Nothing was changed.');
        }

        if (!Auth::user()) {
            Auth::setUser($user);
        }

        $kept = $this->save($match, (string) $request->string('decision'), $request->has('units') ? (float) $request->get('units') : null, trim((string) $request->string('note')));
        $row  = $this->rows($this->base($user->group)->where('master_asset_competitor_products.id', $match->id), $user->group)->first();

        $difference = $row->difference === null ? null : (int) $row->difference;
        $outOfRange = $match->status === MasterAssetCompetitorProductStatusEnum::CONFIRMED && $difference !== null
            && ($difference < self::OUT_OF_RANGE[0] || $difference > self::OUT_OF_RANGE[1]);

        return Response::json(array_filter([
            'match_id'            => $match->id,
            'status'              => $match->status->value,
            'is_same_item'        => $match->is_same_item,
            'units_on_match'      => $match->competitor_units === null ? null : (float) $match->competitor_units,
            'units_verified'      => $match->units_verified,
            'comparison'          => $this->comparison($row),
            'kept_as_note_only'   => $kept,
            'still_out_of_range'  => $outOfRange ? 'Their price per unit is still far from ours with these units. Read both listings again and save a corrected verdict, or keep it and say in the note what proves it.' : null,
        ], fn ($value) => $value !== null));
    }

    /**
     * @return string|null why a person's decision stood and the verdict was kept as a note only
     */
    private function save(MasterAssetCompetitorProduct $match, string $decision, ?float $units, string $note): ?string
    {
        $staffReviewed = $match->reviewed_by_user_id !== null;
        $staffUnits    = $this->unitsSetByStaff(collect([$match]))->isNotEmpty();
        $kept          = null;
        $changes       = ['checked_at' => now(), 'check_note' => $note];

        if ($decision === 'different_item') {
            if ($staffReviewed || $staffUnits) {
                $kept = $staffReviewed ? 'A person reviewed this match, so it was not rejected.' : 'A person entered the units of this match, so it was not rejected.';
            } else {
                $changes += ['status' => MasterAssetCompetitorProductStatusEnum::REJECTED, 'is_same_item' => false];
            }
        }

        if ($decision === 'same_item') {
            if ($staffReviewed && $match->status === MasterAssetCompetitorProductStatusEnum::REJECTED) {
                $kept = 'A person rejected this match, so it was not confirmed.';
            } elseif ($staffUnits && abs((float) $match->competitor_units - $units) > 0.0005) {
                $kept    = 'A person entered the units of this match, so they were not changed.';
                $changes += ['status' => MasterAssetCompetitorProductStatusEnum::CONFIRMED, 'is_same_item' => true];
            } else {
                $changes += ['status' => MasterAssetCompetitorProductStatusEnum::CONFIRMED, 'is_same_item' => true, 'competitor_units' => $units, 'units_verified' => true];
            }
        }

        $match->update($changes);

        return $kept;
    }

    /**
     * @return array{remaining: array<string, int>, matches: array<int, array<string, mixed>>}
     */
    private function queue(Group $group, int $limit): array
    {
        $rows         = $this->rows($this->unchecked($group)->where(fn (QueryBuilder $query) => $query->whereNotNull('competitor_products.page_read_at')->orWhereNull('competitor_products.url')->orWhere('competitors.prices_by_hand', true))->orderBy('stage')->orderBy('master_asset_competitor_products.id')->limit($limit), $group);
        $beingRead    = $this->readNextListings($group, $limit);
        $masterAssets = MasterAsset::whereIn('id', $rows->pluck('master_asset_id'))->with(['tradeUnits:id,code,name', 'masterFamily:id,name'])->get()->keyBy('id');
        $staffUnits   = $this->unitsSetByStaff($rows);

        $remaining = DB::query()
            ->fromSub($this->unchecked($group)->selectRaw($this->stageSql($group).' as stage'), 'unchecked')
            ->selectRaw('stage, count(*) as number')
            ->groupBy('stage')
            ->pluck('number', 'stage');

        return [
            'listings_being_read' => $beingRead,
            'remaining'           => collect([1, 2, 3, 4])->mapWithKeys(fn (int $stage) => ["stage_$stage" => (int) ($remaining[$stage] ?? 0)])->all(),
            'matches'   => $rows->map(function (object $row) use ($masterAssets, $staffUnits) {
                /** @var MasterAsset $masterAsset */
                $masterAsset = $masterAssets[$row->master_asset_id];
                $dimensions  = $masterAsset->marketing_dimensions ?? [];
                $data        = json_decode((string) $row->competitor_data, true) ?: [];

                return [
                    'match_id'     => $row->id,
                    'stage'        => (int) $row->stage,
                    'status'       => $row->status,
                    'is_same_item' => (bool) $row->is_same_item,
                    'ours'         => array_filter([
                        'code'        => $masterAsset->code,
                        'name'        => $masterAsset->name,
                        'unit'        => $masterAsset->unit,
                        'units'       => (float) $masterAsset->units,
                        'trade_units' => $masterAsset->tradeUnits->map(fn ($tradeUnit) => ['code' => $tradeUnit->code, 'name' => $tradeUnit->name, 'quantity' => (float) $tradeUnit->pivot->quantity])->all(),
                        'weight_g'    => $masterAsset->marketing_weight,
                        'size'        => trim(implode(' x ', array_filter([$dimensions['l'] ?? null, $dimensions['w'] ?? null, $dimensions['h'] ?? null])).' '.($dimensions['units'] ?? '')),
                        'family'      => $masterAsset->masterFamily?->name,
                        'description' => mb_substr(trim(strip_tags((string) $masterAsset->description)), 0, 600),
                        'image'       => data_get($masterAsset->web_images, 'main.gallery.png') ?? data_get($masterAsset->web_images, 'all.0.gallery.png'),
                        'pack_price'  => $row->our_unit_price === null ? null : round((float) $row->our_unit_price * max((float) $masterAsset->units, 1), 2),
                    ], fn ($value) => $value !== null && $value !== '' && $value !== []),
                    'theirs'       => array_filter([
                        'competitor'     => $row->competitor_name,
                        'sells_to'       => $row->sells_to,
                        'name'           => $row->competitor_product_name,
                        'url'            => $row->competitor_product_url,
                        'image'          => $row->competitor_image_url,
                        'pack_price'     => (float) $row->competitor_price,
                        'units_read'     => (float) $row->units_read,
                        'units_on_match' => $row->competitor_units === null ? null : (float) $row->competitor_units,
                        'page'           => $row->page_text,
                        'weight_g'       => $data['weight_g'] ?? null,
                        'description'    => isset($data['description']) ? mb_substr(trim(strip_tags((string) $data['description'])), 0, 600) : null,
                        'minimum_order'  => $row->minimum_order,
                    ], fn ($value) => $value !== null && $value !== ''),
                    'comparison'   => $this->comparison($row),
                    'staff'        => ['reviewed' => $row->reviewed_by_user_id !== null, 'units_set_by_staff' => $staffUnits->contains($row->id)],
                ];
            })->all(),
        ];
    }

    /**
     * Has the listings of the next unread matches read in the background, so they are ready on a later call:
     * the pages are opened by our own browser, a few minutes for a batch. A competitor whose prices are
     * checked by hand is never visited, so its matches are judged on what staff recorded.
     */
    private function readNextListings(Group $group, int $limit): int
    {
        $next = $this->unchecked($group)
            ->whereNull('competitor_products.page_read_at')
            ->whereNotNull('competitor_products.url')
            ->where('competitors.prices_by_hand', false)
            ->selectRaw('competitor_products.id, competitor_products.competitor_id, '.$this->stageSql($group).' as stage')
            ->orderBy('stage')
            ->orderBy('master_asset_competitor_products.id')
            ->limit(2 * $limit)
            ->get();

        foreach ($next->groupBy('competitor_id') as $competitorId => $products) {
            ReadCompetitorListings::dispatch(Competitor::find($competitorId), $products->pluck('id')->unique()->values()->all());
        }

        return $next->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function comparison(object $row): array
    {
        return [
            'currency'                   => $row->currency_code,
            'our_unit_price'             => $row->our_unit_price === null ? null : (float) $row->our_unit_price,
            'their_unit_price'           => $row->competitor_unit_price === null ? null : (float) $row->competitor_unit_price,
            'difference_pct'             => $row->difference === null ? null : (int) $row->difference,
            'compared_pack_against_pack' => $row->compared_as_units !== null,
        ];
    }

    private function base(Group $group): QueryBuilder
    {
        return DB::table('master_asset_competitor_products')
            ->join('master_assets', 'master_assets.id', '=', 'master_asset_competitor_products.master_asset_id')
            ->join('competitor_products', 'competitor_products.id', '=', 'master_asset_competitor_products.competitor_product_id')
            ->join('competitors', 'competitors.id', '=', 'competitor_products.competitor_id')
            ->where('master_assets.group_id', $group->id);
    }

    private function unchecked(Group $group): QueryBuilder
    {
        return $this->base($group)
            ->where(fn (QueryBuilder $query) => $query->whereNull('master_asset_competitor_products.checked_at')
                ->orWhere(fn (QueryBuilder $query) => $query->where('master_asset_competitor_products.units_verified', false)
                    ->where(fn (QueryBuilder $query) => $query->whereNull('competitor_products.page_read_at')->orWhereColumn('competitor_products.page_read_at', '>', 'master_asset_competitor_products.checked_at'))))
            ->where('master_asset_competitor_products.status', '!=', MasterAssetCompetitorProductStatusEnum::REJECTED->value)
            ->whereNotNull('competitor_products.price');
    }

    private function stageSql(Group $group): string
    {
        $sql = $this->competitorPriceSql($group);

        return "(CASE WHEN master_asset_competitor_products.status = 'confirmed' AND ({$sql['same_pack']} IS NOT NULL OR {$sql['difference']} NOT BETWEEN ".self::OUT_OF_RANGE[0].' AND '.self::OUT_OF_RANGE[1].") THEN 1
            WHEN master_asset_competitor_products.status = 'confirmed' THEN 2
            WHEN master_asset_competitor_products.is_same_item THEN 3
            ELSE 4 END)";
    }

    /**
     * @return Collection<int, object>
     */
    private function rows(QueryBuilder $query, Group $group): Collection
    {
        $sql = $this->competitorPriceSql($group);

        return $query
            ->select([
                'master_asset_competitor_products.id',
                'master_asset_competitor_products.master_asset_id',
                'master_asset_competitor_products.status',
                'master_asset_competitor_products.is_same_item',
                'master_asset_competitor_products.competitor_units',
                'master_asset_competitor_products.units_verified',
                'master_asset_competitor_products.reviewed_by_user_id',
                'competitors.name as competitor_name',
                'competitors.sells_to',
                'competitor_products.name as competitor_product_name',
                'competitor_products.url as competitor_product_url',
                'competitor_products.image_url as competitor_image_url',
                'competitor_products.price as competitor_price',
                'competitor_products.units as units_read',
                'competitor_products.data as competitor_data',
                'competitor_products.page_text',
                'competitor_products.minimum_order',
            ])
            ->selectRaw($this->stageSql($group)." as stage, {$sql['ours']} as our_unit_price, {$sql['theirs']} as competitor_unit_price, {$sql['difference']} as difference, {$sql['currency']} as currency_code, {$sql['same_pack']} as compared_as_units")
            ->get();
    }

    /**
     * The matches whose units a person typed: units on the match that the check did not verify and that a
     * signed-in user saved.
     *
     * @param  Collection<int, object>  $matches
     * @return Collection<int, int> match ids
     */
    private function unitsSetByStaff(Collection $matches): Collection
    {
        $candidates = $matches->filter(fn (object $match) => $match->competitor_units !== null && !$match->units_verified)->pluck('id');
        if ($candidates->isEmpty()) {
            return collect();
        }

        return DB::table('audits')
            ->where('auditable_type', 'MasterAssetCompetitorProduct')
            ->whereIn('auditable_id', $candidates)
            ->whereNotNull('user_id')
            ->whereRaw("jsonb_exists(new_values::jsonb, 'competitor_units')")
            ->distinct()
            ->pluck('auditable_id')
            ->map(fn ($id) => (int) $id);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'match_id' => $schema->integer()->description('The match to save a verdict on; leave out to get the next matches to check'),
            'decision' => $schema->string()->enum(['same_item', 'different_item', 'cannot_tell'])->description('Required with match_id'),
            'units'    => $schema->number()->description('With same_item: how many of OUR units their pack holds'),
            'note'     => $schema->string()->description('Required with match_id: one or two plain sentences on what their listing says, for our pricing staff'),
            'limit'    => $schema->integer()->description('How many matches to return, 1 to '.self::MAX_LIMIT.', default '.self::DEFAULT_LIMIT),
        ];
    }
}
