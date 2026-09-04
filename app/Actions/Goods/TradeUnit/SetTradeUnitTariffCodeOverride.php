<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnit;

use App\Actions\Catalogue\Product\Hydrators\ProductHydrateHeathAndSafetyFromTradeUnits;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithAccountingEditAuthorisation;
use App\Models\Goods\TradeUnit;
use App\Models\Goods\TradeUnitTariffCodeOverride;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class SetTradeUnitTariffCodeOverride extends OrgAction
{
    use WithAccountingEditAuthorisation;

    private TradeUnit $tradeUnit;

    public function handle(TradeUnit $tradeUnit, Organisation $organisation, User $approvedBy, array $modelData): TradeUnitTariffCodeOverride
    {
        $override = TradeUnitTariffCodeOverride::updateOrCreate(
            [
                'trade_unit_id'   => $tradeUnit->id,
                'organisation_id' => $organisation->id,
            ],
            [
                'group_id'            => $tradeUnit->group_id,
                'national_extension'  => $modelData['national_extension'],
                'reason'              => $modelData['reason'],
                'approved_by_user_id' => $approvedBy->id,
                'approved_at'         => now(),
            ]
        );

        $this->hydrateProductsTariffCode($tradeUnit, $organisation);

        return $override;
    }

    public function hydrateProductsTariffCode(TradeUnit $tradeUnit, Organisation $organisation): void
    {
        $tradeUnit->unsetRelation('tariffCodeOverrides');
        foreach ($tradeUnit->products()->where('products.organisation_id', $organisation->id)->get() as $product) {
            ProductHydrateHeathAndSafetyFromTradeUnits::run($product, ['tariff_code']);
        }
    }

    public function rules(): array
    {
        return [
            'national_extension' => ['required', 'regex:/^\d{2,4}$/'],
            'reason'             => ['required', 'string', 'max:1000'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if (!$this->tradeUnit->getTariffCodeHeading()) {
            $validator->errors()->add('national_extension', __('Set a shared tariff code with a 6-digit HS heading on the trade unit first'));
        }
    }

    public function asController(TradeUnit $tradeUnit, Organisation $organisation, ActionRequest $request): TradeUnitTariffCodeOverride
    {
        $this->tradeUnit = $tradeUnit;
        $this->initialisation($organisation, $request);

        return $this->handle($tradeUnit, $organisation, $request->user(), $this->validatedData);
    }

    public function action(TradeUnit $tradeUnit, Organisation $organisation, User $approvedBy, array $modelData): TradeUnitTariffCodeOverride
    {
        $this->asAction  = true;
        $this->tradeUnit = $tradeUnit;
        $this->initialisation($organisation, $modelData);

        return $this->handle($tradeUnit, $organisation, $approvedBy, $this->validatedData);
    }
}
