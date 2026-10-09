<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDeliveryClaim;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Enums\GoodsIn\StockDeliveryClaimStateEnum;
use App\Models\GoodsIn\StockDeliveryClaim;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class UpdateStockDeliveryClaim extends OrgAction
{
    use WithProcurementEditAuthorisation;

    private StockDeliveryClaim $claim;

    public function handle(StockDeliveryClaim $claim, array $modelData): StockDeliveryClaim
    {
        $photos = Arr::pull($modelData, 'photos', []);

        if (isset($modelData['state'])) {
            $state = StockDeliveryClaimStateEnum::from($modelData['state']);
            if ($state === StockDeliveryClaimStateEnum::SENT && !$claim->sent_at) {
                $modelData['sent_at'] = now();
            }
            $modelData['closed_at'] = $state->isClosed() ? ($claim->closed_at ?? now()) : null;
        }

        $claim->update($modelData);

        StoreStockDeliveryClaim::savePhotos($claim, $photos);

        return $claim;
    }

    public function rules(): array
    {
        return [
            'state'                 => ['sometimes', 'required', Rule::enum(StockDeliveryClaimStateEnum::class)],
            'quantity'              => ['sometimes', 'required', 'numeric', 'gt:0'],
            'amount'                => ['sometimes', 'required', 'numeric', 'min:0'],
            'credit_note_reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'credit_note_amount'    => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'credit_note_date'      => ['sometimes', 'nullable', 'date'],
            'notes'                 => ['sometimes', 'nullable', 'string', 'max:5000'],
            'photos'                => ['sometimes', 'array', 'max:10'],
            'photos.*'              => ['file', 'mimes:jpg,jpeg,png,webp,heic,pdf', 'max:20480'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->get('state') !== StockDeliveryClaimStateEnum::CREDIT_RECEIVED->value) {
            return;
        }

        foreach (['credit_note_reference', 'credit_note_amount'] as $field) {
            if (blank($this->has($field) ? $this->get($field) : $this->claim->{$field})) {
                $validator->errors()->add($field, __('A claim is credited with a credit note: enter its number and amount'));
            }
        }
    }

    public function asController(StockDeliveryClaim $stockDeliveryClaim, ActionRequest $request): StockDeliveryClaim
    {
        $this->claim = $stockDeliveryClaim;
        $this->initialisation($stockDeliveryClaim->organisation, $request);

        return $this->handle($stockDeliveryClaim, $this->validatedData);
    }

    public function action(StockDeliveryClaim $stockDeliveryClaim, array $modelData): StockDeliveryClaim
    {
        $this->claim    = $stockDeliveryClaim;
        $this->asAction = true;
        $this->initialisation($stockDeliveryClaim->organisation, $modelData);

        return $this->handle($stockDeliveryClaim, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
