<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\Masters\MasterAsset\GenerateMasterAssetPriceTips;
use App\Actions\Masters\MasterAsset\UpdateMasterAssetPrices;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\SysAdmin\McpChange\McpChangeTypeEnum;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

/**
 * Master prices are gated by can_use_mcp_prices on the user's account, on top of the master edit
 * permission the same change needs in the UI. Prices are saved through the normal master price
 * update, so they reach every shop following the master exactly as a save in the price editor does.
 */
#[Description('Shows or saves master prices: the price of a master product (one outer) in each main currency, which every shop following the master sells at. Without prices it shows the products of a master family, or the given codes, with their current prices, units per outer, cost and whether the price was changed by hand in the last 30 days. With prices it saves them: give only main currencies (the tool lists them); the other currencies follow by the master shop exchange rate, except those set by hand, which are left alone and reported. A change above +100% or below -60%, or a first price in a currency the product has none in, is refused unless allow_large_changes is true, to catch a price given per unit instead of per outer. Only save after showing the user the old and new price of every product and they confirmed in their own words, passing their request text. Every save is logged in AI changes and can be reverted there. Only for users enrolled to change prices through their assistant.')]
class MasterPricesTool extends Tool
{
    use WithMcpPermissions;
    use WithMcpChangeLog;

    public const int MAX_PRODUCTS = 200;

    public const float LARGE_RISE = 2.0;

    public const float LARGE_CUT = 0.4;

    public function handle(Request $request): Response
    {
        $request->validate([
            'master_shop'         => ['required', 'string'],
            'family'              => ['sometimes', 'string'],
            'codes'               => ['sometimes', 'array', 'max:'.self::MAX_PRODUCTS],
            'codes.*'             => ['string'],
            'prices'              => ['sometimes', 'array', 'min:1', 'max:'.self::MAX_PRODUCTS],
            'prices.*.code'       => ['required', 'string'],
            'prices.*.prices'     => ['required', 'array', 'min:1'],
            'prices.*.prices.*'   => ['numeric', 'gt:0'],
            'request_text'        => ['required_with:prices', 'string', 'max:4000'],
            'allow_large_changes' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();
        if (!$user?->can_use_mcp_prices) {
            return Response::error('Changing prices is not enabled for this user. Do not retry; an administrator enrols it on the user\'s edit page.');
        }
        if (!$this->userCan($request, 'masters.edit')) {
            return Response::error('Master prices need master edit permission, which this user does not have. Nothing was changed.');
        }

        $given      = strtolower((string) $request->string('master_shop'));
        $masterShop = MasterShop::where('group_id', $user->group_id)
            ->where(fn ($query) => $query->whereRaw('lower(slug) = ?', [$given])->orWhereRaw('lower(code) = ?', [$given]))
            ->first();
        if (!$masterShop) {
            return Response::error("'$given' is not a master shop. Master shops: ".MasterShop::where('group_id', $user->group_id)->pluck('code')->implode(', ').'.');
        }

        if (!$request->has('prices')) {
            return $this->show($request, $masterShop);
        }

        $exchanges = collect($masterShop->price_exchanges ?? []);
        $majors    = $exchanges->filter(fn (array $exchange) => $exchange['is_major'] ?? false)->keys();
        $entries   = collect($request->get('prices'))->keyBy(fn (array $entry) => strtolower(trim($entry['code'])));
        if ($entries->count() !== count($request->get('prices'))) {
            return Response::error('Nothing was changed. A product code is given more than once; give each product once.');
        }
        $assets    = $this->masterProducts($masterShop)->whereIn(DB::raw('lower(code)'), $entries->keys()->all())->get()->keyBy(fn (MasterAsset $masterAsset) => strtolower($masterAsset->code));

        $missing = $entries->keys()->diff($assets->keys());
        if ($missing->isNotEmpty()) {
            return Response::error('Unknown master product codes in '.$masterShop->code.': '.$missing->map(fn (string $key) => trim($entries[$key]['code']))->implode(', ').'. Nothing was changed.');
        }

        $notMajor = $entries->flatMap(fn (array $entry) => array_keys($entry['prices']))->unique()->diff($majors);
        if ($notMajor->isNotEmpty()) {
            return Response::error('Nothing was changed. Give prices only in the main currencies of '.$masterShop->code.' ('.$majors->implode(', ').'); '.$notMajor->implode(', ').' follow by exchange rate.');
        }

        $payloads = [];
        $changes  = [];
        $large    = [];
        $handSet  = [];
        foreach ($entries as $key => $entry) {
            /** @var MasterAsset $masterAsset */
            $masterAsset = $assets->get($key);
            $current     = $masterAsset->master_prices ?? [];
            $payload     = [];

            foreach ($entry['prices'] as $currencyCode => $value) {
                $old                    = (float) data_get($current, "$currencyCode.value", 0);
                $payload[$currencyCode] = ['value' => (float) $value];
                $changes[$masterAsset->code][$currencyCode] = ['from' => $old ?: null, 'to' => (float) $value, 'change_pct' => $old > 0 ? round(100 * ($value / $old - 1), 1) : null];

                if ($old <= 0 || $value / $old > self::LARGE_RISE || $value / $old < self::LARGE_CUT) {
                    $large[] = "$masterAsset->code $currencyCode ".($old > 0 ? $old : 'no price')." to $value";
                }
            }

            foreach ($exchanges as $currencyCode => $exchange) {
                $major = $exchange['major'] ?? null;
                if (($exchange['is_major'] ?? false) || !isset($payload[$major]) || !($exchange['exchange'] ?? null)) {
                    continue;
                }
                if (data_get($current, "$currencyCode.independent")) {
                    $handSet[$masterAsset->code][] = $currencyCode;

                    continue;
                }
                $payload[$currencyCode] = ['value' => (float) formatPrice($payload[$major]['value'], (float) $exchange['exchange'], (int) ($exchange['fraction_digits'] ?? 2), isset($exchange['increment']) ? (float) $exchange['increment'] : null)];
            }

            $payloads[$masterAsset->id] = $payload;
        }

        if ($large && !$request->boolean('allow_large_changes')) {
            return Response::error('Nothing was changed. These are large changes or first prices, often a price given per unit instead of per outer: '.implode('; ', $large).'. Show them to the user and, once confirmed, call again with allow_large_changes=true.');
        }

        try {
            $this->recordChange(
                $request,
                McpChangeTypeEnum::MASTER_PRICES,
                'Master prices in '.$masterShop->code.': '.$assets->pluck('code')->implode(', '),
                ['master_asset_ids' => $assets->pluck('id')->sort()->values()->all()],
                function () use ($assets, $payloads) {
                    foreach ($assets as $masterAsset) {
                        $updatePrices                    = UpdateMasterAssetPrices::make();
                        $updatePrices->forceAsyncCascade = true;
                        $updatePrices->action($masterAsset, ['master_prices' => $payloads[$masterAsset->id]]);
                    }
                },
                ['master_shop' => $masterShop->code]
            );
        } catch (ValidationException $exception) {
            return Response::error(implode(' ', $exception->validator->errors()->all()).' Check which prices were saved before trying again.');
        }

        return Response::json([
            'changed'                   => (bool) $this->mcpChange,
            'change_log_id'             => $this->mcpChange?->id,
            'master_shop'               => $masterShop->code,
            'prices'                    => $changes,
            'hand_set_currencies_left'  => $handSet,
            'note'                      => 'Shops following the master pick the new prices up in the next minutes. The products get no price tip for 30 days.',
        ]);
    }

    private function show(Request $request, MasterShop $masterShop): Response
    {
        $query = $this->masterProducts($masterShop);

        if ($request->has('codes')) {
            $query->whereIn(DB::raw('lower(code)'), array_map(fn ($code) => strtolower(trim($code)), $request->get('codes')));
        } elseif ($request->has('family')) {
            $masterFamily = MasterProductCategory::where('master_shop_id', $masterShop->id)
                ->where('type', MasterProductCategoryTypeEnum::FAMILY)
                ->whereRaw('lower(code) = ?', [strtolower((string) $request->string('family'))])
                ->first();
            if (!$masterFamily) {
                return Response::error("'{$request->string('family')}' is not a master family in {$masterShop->code}.");
            }
            $query->where('master_family_id', $masterFamily->id);
        } else {
            return Response::error('Give a master family code or a list of master product codes.');
        }

        $masterAssets  = $query->orderBy('code')->limit(self::MAX_PRODUCTS)->get();
        $exchanges     = collect($masterShop->price_exchanges ?? []);
        $majors        = $exchanges->filter(fn (array $exchange) => $exchange['is_major'] ?? false)->keys();
        $changedByHand = GenerateMasterAssetPriceTips::make()->lastManualPriceChanges($masterAssets->pluck('id')->all());

        return Response::json([
            'master_shop'      => $masterShop->code,
            'main_currencies'  => $majors->all(),
            'other_currencies' => $exchanges->reject(fn (array $exchange) => $exchange['is_major'] ?? false)->map(fn (array $exchange) => ['follows' => $exchange['major'] ?? null, 'exchange' => $exchange['exchange'] ?? null])->all(),
            'cost_currency'    => $masterShop->group->currency->code,
            'products'         => $masterAssets->map(fn (MasterAsset $masterAsset) => [
                'code'                         => $masterAsset->code,
                'name'                         => $masterAsset->name,
                'units_per_outer'              => (float) $masterAsset->units,
                'prices'                       => $this->values($masterAsset, $majors),
                'hand_set_currencies'          => collect($masterAsset->master_prices ?? [])->filter(fn ($entry) => $entry['independent'] ?? false)->keys()->diff($majors)->values()->all(),
                'cost'                         => $masterAsset->effective_cost !== null ? round((float) $masterAsset->effective_cost, 2) : null,
                'price_changed_by_hand_on'     => $changedByHand->get($masterAsset->id),
            ])->all(),
        ]);
    }

    /**
     * @return array<string, float|null>
     */
    private function values(MasterAsset $masterAsset, Collection $currencies): array
    {
        return $currencies->mapWithKeys(fn (string $currencyCode) => [
            $currencyCode => ($value = data_get($masterAsset->master_prices, "$currencyCode.value")) !== null ? (float) $value : null,
        ])->all();
    }

    private function masterProducts(MasterShop $masterShop)
    {
        return MasterAsset::where('master_shop_id', $masterShop->id)
            ->where('type', MasterAssetTypeEnum::PRODUCT)
            ->where('status', true);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'master_shop'         => $schema->string()->description('Master shop slug or code, e.g. aw')->required(),
            'family'              => $schema->string()->description('Master family code: shows every product in it. Not needed when saving'),
            'codes'               => $schema->array()->items($schema->string())->description('Master product codes to show, up to 200, instead of a family'),
            'prices'              => $schema->array()->items($schema->object())->description('Prices to save, up to 200 products: [{"code": "ABC-01", "prices": {"GBP": 6.5, "EUR": 7.8}}]. Price of one outer, main currencies only. Omit to only show'),
            'request_text'        => $schema->string()->description('The user\'s request, verbatim; required when saving'),
            'allow_large_changes' => $schema->boolean()->description('True only after the user confirmed the large changes or first prices that a first call refused'),
        ];
    }
}
