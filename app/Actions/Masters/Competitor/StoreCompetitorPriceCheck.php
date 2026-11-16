<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Nov 2026 17:03:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\Competitor;

use App\Actions\Helpers\Media\SaveModelImage;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Enums\Masters\Competitor\MasterAssetCompetitorProductStatusEnum;
use App\Models\Masters\Competitor;
use App\Models\Masters\CompetitorPriceCheck;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterAssetCompetitorProduct;
use App\Models\SysAdmin\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

/**
 * A person records the price they see for one of our products on a marketplace that blocks automated
 * visits (INI-217). Recording a listing says it is the same item as ours (as a tick does since HELP-4528),
 * so it becomes the competitor's product and a confirmed same-item match, and the usual
 * price comparison and dashboard count it, and every check is kept as history with the link to the
 * listing and a screenshot of it showing the price, so anyone can see it with their own eyes, also
 * after the listing changes or disappears. A competitor read by its feed or search takes price checks too, for the
 * sizes and products the reading misses (HELP-4691); the feed import keeps them.
 */
class StoreCompetitorPriceCheck extends OrgAction
{
    use WithMastersEditAuthorisation;

    private Competitor $competitor;

    public function handle(Competitor $competitor, array $modelData, ?User $user = null): CompetitorPriceCheck
    {
        $masterAsset = $this->masterAsset($competitor, $modelData['master_asset_code']);
        $units       = (float) ($modelData['units'] ?? 1);

        $screenshot = Arr::pull($modelData, 'screenshot');

        return DB::transaction(function () use ($competitor, $modelData, $user, $masterAsset, $units, $screenshot) {
            $competitorProduct = $competitor->products()->updateOrCreate(
                ['code' => $modelData['url']],
                [
                    'url'        => $modelData['url'],
                    'name'       => $modelData['name'],
                    'price'      => $modelData['price'],
                    'units'      => $units,
                    'fetched_at' => now(),
                ]
            );

            MasterAssetCompetitorProduct::updateOrCreate(
                ['master_asset_id' => $masterAsset->id, 'competitor_product_id' => $competitorProduct->id],
                [
                    'master_shop_id'      => $masterAsset->master_shop_id,
                    'is_same_item'        => true,
                    'status'              => MasterAssetCompetitorProductStatusEnum::CONFIRMED,
                    'competitor_units'    => null,
                    'reviewed_by_user_id' => $user?->id,
                    'reviewed_at'         => now(),
                ]
            );

            $competitor->update([
                'number_products' => $competitor->products()->count(),
                'fetched_at'      => now(),
            ]);

            [$ours, $theirs, $currency] = GetConfirmedCompetitorPrices::unitPrices($masterAsset, $competitor, (float) $modelData['price'], $units, $competitor->group->currency);

            $priceCheck = $competitor->priceChecks()->create([
                'group_id'              => $competitor->group_id,
                'competitor_product_id' => $competitorProduct->id,
                'master_asset_id'       => $masterAsset->id,
                'price'                 => $modelData['price'],
                'units'                 => $units,
                'delivery_days'         => $modelData['delivery_days'] ?? null,
                'currency_id'           => $currency->id,
                'our_unit_price'        => $ours === null ? null : round($ours, 4),
                'their_unit_price'      => $theirs === null ? null : round($theirs, 4),
                'difference_pct'        => $ours && $theirs !== null ? (int) round(100 * ($theirs / $ours - 1)) : null,
                'checked_by_user_id'    => $user?->id,
                'checked_at'            => now(),
            ]);

            if ($screenshot) {
                SaveModelImage::run(
                    model: $priceCheck,
                    imageData: [
                        'path'         => $screenshot->getPathName(),
                        'originalName' => Str::slug($competitor->name.' '.$masterAsset->code).'-'.now()->format('Y-m-d').'.'.$screenshot->getClientOriginalExtension(),
                        'extension'    => $screenshot->getClientOriginalExtension(),
                    ]
                );
            }

            return $priceCheck;
        });
    }

    private function masterAsset(Competitor $competitor, string $code): ?MasterAsset
    {
        return MasterAsset::where('master_shop_id', $competitor->master_shop_id)
            ->where('status', true)
            ->whereRaw('lower(code) = lower(?)', [trim($code)])
            ->first();
    }

    public function rules(): array
    {
        return [
            'master_asset_code' => [
                'required',
                'string',
                'max:64',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (!$this->masterAsset($this->competitor, (string) $value)) {
                        $fail(__('No active product with this code in :shop', ['shop' => $this->competitor->masterShop->name]));
                    }
                },
            ],
            'url'           => ['required', 'url:http,https', 'max:2000'],
            'name'          => ['required', 'string', 'max:500'],
            'price'         => ['required', 'numeric', 'min:0', 'max:1000000'],
            'units'         => ['sometimes', 'numeric', 'min:1', 'max:100000'],
            'delivery_days' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:365'],
            'screenshot'    => ['required', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    public function asController(Competitor $competitor, ActionRequest $request): CompetitorPriceCheck
    {
        $this->competitor = $competitor;
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($competitor, $this->validatedData, $request->user());
    }

    public function action(Competitor $competitor, array $modelData, ?User $user = null): CompetitorPriceCheck
    {
        $this->asAction   = true;
        $this->competitor = $competitor;
        $this->initialisationFromGroup($competitor->group, $modelData);

        return $this->handle($competitor, $this->validatedData, $user);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
