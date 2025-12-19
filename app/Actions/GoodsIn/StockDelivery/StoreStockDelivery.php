<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 09 May 2023 14:50:49 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\GoodsIn\StockDelivery\Traits\HasStockDeliveryHydrators;
use App\Actions\OrgAction;
use App\Actions\Procurement\WithNoStrictProcurementOrderRules;
use App\Actions\Procurement\WithPrepareDeliveryStoreFields;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Enums\Procurement\StockDelivery\StockDeliveryStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\OrgSupplier;
use App\Models\Production\Production;
use App\Rules\IUnique;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class StoreStockDelivery extends OrgAction
{
    use HasStockDeliveryHydrators;
    use WithPrepareDeliveryStoreFields;
    use WithNoStrictRules;
    use WithNoStrictProcurementOrderRules;


    private OrgSupplier|OrgAgent|OrgPartner|Production $parent;

    public function handle(OrgSupplier|OrgAgent|OrgPartner|Production $parent, array $modelData): StockDelivery
    {
        data_set($modelData, 'organisation_id', $this->organisation->id);
        data_set($modelData, 'group_id', $this->organisation->group_id);

        $modelData = $this->prepareDeliveryStoreFields($parent, $modelData);

        /** @var StockDelivery $stockDelivery */
        $stockDelivery = $parent->stockDeliveries()->create($modelData);

        $this->runStockDeliveryHydrators($stockDelivery);

        return $stockDelivery;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    public function rules(): array
    {
        $rules = [
            'reference' => [
                'sometimes',
                'required',
                $this->strict ? 'alpha_dash' : 'string',
            ],
        ];

        if ($this->strict) {
            $rules['reference'][] = new IUnique(
                table: 'stock_deliveries',
                extraConditions: [
                    ['column' => 'organisation_id', 'value' => $this->organisation->id],
                ]
            );
        }


        if (!$this->strict) {
            $rules = $this->noStrictStoreRules($rules);
            $rules = $this->noStrictProcurementOrderRules($rules);
            $rules = $this->noStrictStockDeliveryRules($rules);
        }




        return $rules;
    }

    public function afterValidator(Validator $validator): void
    {
        $stockDelivery = $this->parent->stockDeliveries()->where('state', StockDeliveryStateEnum::IN_PROCESS)->count();

        if ($this->strict && $stockDelivery >= 1) {
            $validator->errors()->add('stock_delivery', 'Are you sure want to create new supplier delivery?');
        }
    }

    public function prepareForValidation(): void
    {
        if ($this->has('reference')) {
            $this->set('reference', (string)$this->get('reference'));
        }
    }

    public function action(OrgSupplier|OrgAgent|OrgPartner|Production $parent, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true): StockDelivery
    {
        if (!$audit) {
            StockDelivery::disableAuditing();
        }
        $this->asAction       = true;
        $this->parent         = $parent;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;

        $this->initialisation($parent->organisation, $modelData);

        return $this->handle($parent, $modelData);
    }
}
