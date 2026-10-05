<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 09:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Enums\Masters\MasterAsset\MasterAssetPriceTipStatusEnum;
use App\Models\Masters\MasterAssetPriceTip;
use App\Models\SysAdmin\User;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Http\RedirectResponse;

/**
 * Applies a price tip through the normal master price update, with the prices staff reviewed in
 * the editor, or every currency moved by the tip's change when none are given.
 */
class ApplyMasterAssetPriceTip extends OrgAction
{
    use WithMastersEditAuthorisation;

    public function handle(MasterAssetPriceTip $masterAssetPriceTip, array $modelData, ?User $user = null): MasterAssetPriceTip
    {
        if ($masterAssetPriceTip->status !== MasterAssetPriceTipStatusEnum::OPEN) {
            throw ValidationException::withMessages(['status' => __('This price tip is no longer open')]);
        }

        $masterAsset  = $masterAssetPriceTip->masterAsset;
        $factor       = 1 + $masterAssetPriceTip->change / 100;
        $masterPrices = $modelData['master_prices'] ?? collect($masterAsset->master_prices ?? [])
            ->filter(fn ($entry) => ($entry['value'] ?? null) !== null)
            ->map(fn ($entry) => ['value' => round((float) $entry['value'] * $factor, 2)])
            ->all();

        $masterAsset = UpdateMasterAssetPrices::make()->action($masterAsset, ['master_prices' => $masterPrices]);

        $masterAssetPriceTip->update([
            'status'             => MasterAssetPriceTipStatusEnum::APPLIED,
            'applied_price'      => $masterAsset->price,
            'applied_by_user_id' => $user?->id,
            'applied_at'         => now(),
        ]);

        return $masterAssetPriceTip;
    }

    public function rules(): array
    {
        return [
            'master_prices'         => ['sometimes', 'array'],
            'master_prices.*.value' => ['nullable', 'numeric', 'gt:0'],
        ];
    }

    public function asController(MasterAssetPriceTip $masterAssetPriceTip, ActionRequest $request): MasterAssetPriceTip
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($masterAssetPriceTip, $this->validatedData, $request->user());
    }

    public function action(MasterAssetPriceTip $masterAssetPriceTip, array $modelData, ?User $user = null): MasterAssetPriceTip
    {
        $this->asAction = true;
        $this->initialisationFromGroup($masterAssetPriceTip->masterAsset->group, $modelData);

        return $this->handle($masterAssetPriceTip, $this->validatedData, $user);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
