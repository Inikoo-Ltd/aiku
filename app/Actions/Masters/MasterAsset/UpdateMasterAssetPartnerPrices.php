<?php

namespace App\Actions\Masters\MasterAsset;

use App\Actions\Masters\MasterAsset\UI\GetMasterAssetsPartnerPricing;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterAssetPartnerPrice;
use App\Models\Procurement\OrgPartner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Sets or clears the price per outer each partner pays for an Aromatics master product, keyed by
 * the partner's org_partners id and kept in the partner organisation's currency. A null price
 * removes the agreed price, so the partner pays the product price less the hub partner discount.
 */
class UpdateMasterAssetPartnerPrices extends OrgAction
{
    use WithMastersEditAuthorisation;

    private MasterAsset $masterAsset;

    /**
     * @param array{partner_prices: array<int, float|string|null>} $modelData
     */
    public function handle(MasterAsset $masterAsset, array $modelData): MasterAsset
    {
        $partners = GetMasterAssetsPartnerPricing::partners($masterAsset->masterShop)->keyBy('id');

        DB::transaction(function () use ($masterAsset, $modelData, $partners) {
            foreach ($modelData['partner_prices'] as $orgPartnerId => $price) {
                /** @var OrgPartner $orgPartner */
                $orgPartner = $partners->get((int) $orgPartnerId);

                $existing = MasterAssetPartnerPrice::where('master_asset_id', $masterAsset->id)
                    ->where('org_partner_id', $orgPartner->id)
                    ->first();

                if ($price === null || $price === '') {
                    $existing?->delete();

                    continue;
                }

                $values = [
                    'currency_id' => $orgPartner->organisation->currency_id,
                    'price'       => round((float) $price, 2),
                ];

                if ($existing) {
                    $existing->update($values);
                } else {
                    MasterAssetPartnerPrice::create([
                        'group_id'        => $masterAsset->group_id,
                        'master_asset_id' => $masterAsset->id,
                        'org_partner_id'  => $orgPartner->id,
                        ...$values,
                    ]);
                }
            }
        });

        return $masterAsset;
    }

    public function rules(): array
    {
        return [
            'partner_prices'   => ['required', 'array'],
            'partner_prices.*' => ['nullable', 'numeric', 'gt:0'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if (!GetMasterAssetsPartnerPricing::appliesTo($this->masterAsset->masterShop)) {
            $validator->errors()->add('partner_prices', __('Partner prices are only set on Aromatics master products.'));

            return;
        }

        $partnerIds = GetMasterAssetsPartnerPricing::partners($this->masterAsset->masterShop)->pluck('id')->all();
        foreach (array_keys((array) $this->get('partner_prices', [])) as $orgPartnerId) {
            if (!in_array((int) $orgPartnerId, $partnerIds, true)) {
                $validator->errors()->add('partner_prices', __('Unknown partner.'));

                return;
            }
        }
    }

    public function asController(MasterAsset $masterAsset, ActionRequest $request): MasterAsset
    {
        $this->masterAsset = $masterAsset;
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($masterAsset, $this->validatedData);
    }

    /**
     * @param array{partner_prices: array<int, float|string|null>} $modelData
     */
    public function action(MasterAsset $masterAsset, array $modelData): MasterAsset
    {
        $this->asAction    = true;
        $this->masterAsset = $masterAsset;
        $this->initialisationFromGroup($masterAsset->group, $modelData);

        return $this->handle($masterAsset, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
