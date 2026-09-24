<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\ProcurementNote;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Enums\Helpers\Audit\AuditEventEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Procurement\ProcurementNote;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class StoreProcurementNote extends OrgAction
{
    use WithProcurementEditAuthorisation;

    public function handle(PurchaseOrder|StockDelivery $model, array $modelData): ProcurementNote
    {
        return ProcurementNote::create([
            'group_id'        => $model->group_id,
            'organisation_id' => $model->organisation_id,
            'auditable_type'  => class_basename($model),
            'auditable_id'    => $model->id,
            'event'           => AuditEventEnum::NOTE->value,
            'tags'            => ['procurement_notes'],
            'user_type'       => Arr::has($modelData, 'user_id') || !$this->asAction ? 'User' : null,
            'user_id'         => Arr::get($modelData, 'user_id', request()->user()?->id),
            'new_values'      => ['note' => $modelData['note']],
            'data'            => array_filter([
                'author'        => Arr::get($modelData, 'author'),
                'strikethrough' => Arr::get($modelData, 'strikethrough'),
            ]),
            'source_id'       => Arr::get($modelData, 'source_id'),
            'fetched_at'      => Arr::get($modelData, 'source_id') ? now() : null,
            'created_at'      => Arr::get($modelData, 'created_at', now()),
            'updated_at'      => now(),
        ]);
    }

    public function rules(): array
    {
        $rules = [
            'note' => ['required', 'string', 'max:10000'],
        ];

        if (!$this->strict) {
            $rules['user_id']       = ['sometimes', 'nullable', 'integer'];
            $rules['author']        = ['sometimes', 'nullable', 'string'];
            $rules['strikethrough'] = ['sometimes', 'boolean'];
            $rules['source_id']     = ['sometimes', 'string'];
            $rules['created_at']    = ['sometimes', 'date'];
        }

        return $rules;
    }

    public function action(PurchaseOrder|StockDelivery $model, array $modelData, bool $strict = true): ProcurementNote
    {
        $this->asAction = true;
        $this->strict   = $strict;
        $this->initialisation($model->organisation, $modelData);

        return $this->handle($model, $this->validatedData);
    }

    public function inPurchaseOrder(PurchaseOrder $purchaseOrder, ActionRequest $request): void
    {
        $this->initialisation($purchaseOrder->organisation, $request);
        $this->handle($purchaseOrder, $this->validatedData);
    }

    public function inStockDelivery(StockDelivery $stockDelivery, ActionRequest $request): void
    {
        $this->initialisation($stockDelivery->organisation, $request);
        $this->handle($stockDelivery, $this->validatedData);
    }
}
