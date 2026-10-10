<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDeliveryServiceInvoice;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryServiceInvoiceTypeEnum;
use App\Models\GoodsIn\StockDeliveryServiceInvoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class UpdateStockDeliveryServiceInvoice extends OrgAction
{
    use WithStockDeliveryServiceInvoiceAllocations;
    use WithActionUpdate;

    public const array MONEY_FIELDS = ['type', 'currency_id', 'exchange', 'total_amount', 'allocations'];

    private StockDeliveryServiceInvoice $serviceInvoice;

    public function handle(StockDeliveryServiceInvoice $serviceInvoice, array $modelData): StockDeliveryServiceInvoice
    {
        if (!Arr::hasAny($modelData, self::MONEY_FIELDS)) {
            $this->saveAttachments($serviceInvoice, $modelData);
            $serviceInvoice = $this->update($serviceInvoice, Arr::except($modelData, ['attachments']));

            if (Arr::hasAny($modelData, ['issuer', 'reference', 'date'])) {
                $this->syncStockDeliveries($serviceInvoice->stockDeliveries()->where('is_costed', false)->pluck('stock_deliveries.id')->all());
            }

            return $serviceInvoice;
        }

        $currencyId = (int) Arr::get($modelData, 'currency_id', $serviceInvoice->currency_id);
        $exchange   = $this->orgExchange($currencyId, Arr::get($modelData, 'exchange', $currencyId === $serviceInvoice->currency_id ? (float) $serviceInvoice->exchange : null));
        $total      = round((float) Arr::get($modelData, 'total_amount', $serviceInvoice->total_amount), 2);

        $serviceInvoice = DB::transaction(function () use ($serviceInvoice, $modelData, $currencyId, $exchange, $total) {
            $previous    = $serviceInvoice->stockDeliveries()->pluck('stock_delivery_service_invoice_allocations.amount', 'stock_deliveries.id')->map(fn ($amount) => (float) $amount)->all();
            $previousIds = array_keys($previous);
            $rescaled    = self::largestRemainderSplit($previousIds, $previous, $total);
            $allocations = Arr::get($modelData, 'allocations')
                ?? array_map(fn (int $id) => ['stock_delivery_id' => $id, 'amount' => $rescaled[$id]['amount']], $previousIds);

            $serviceInvoice = $this->update($serviceInvoice, array_merge(
                Arr::except($modelData, ['allocations', 'attachments']),
                [
                    'currency_id'      => $currencyId,
                    'exchange'         => $exchange,
                    'total_amount'     => $total,
                    'org_total_amount' => round($total * $exchange, 2),
                ]
            ));

            $serviceInvoice->stockDeliveries()->sync(self::splitAllocations($allocations, $total));
            $this->syncStockDeliveries(array_merge($previousIds, array_column($allocations, 'stock_delivery_id')));

            return $serviceInvoice;
        });

        $this->saveAttachments($serviceInvoice, $modelData);

        return $serviceInvoice;
    }

    public function rules(): array
    {
        return array_merge($this->allocationRules(), [
            'type'         => ['sometimes', Rule::enum(StockDeliveryServiceInvoiceTypeEnum::class)],
            'issuer'       => ['sometimes', 'string', 'max:255'],
            'reference'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'date'         => ['sometimes', 'date'],
            'currency_id'  => ['sometimes', 'exists:currencies,id'],
            'exchange'     => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'total_amount' => ['sometimes', 'numeric', 'gt:0', 'max:999999999999'],
            'paid_at'      => ['sometimes', 'nullable', 'date'],
            'notes'        => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);
    }

    public function afterValidator(Validator $validator): void
    {
        if (!collect(self::MONEY_FIELDS)->contains(fn (string $field) => $this->has($field))) {
            return;
        }

        $total = is_numeric($this->get('total_amount')) ? (float) $this->get('total_amount') : (float) $this->serviceInvoice->total_amount;
        $this->validateAllocations($validator, $this->serviceInvoice->stockDeliveries()->pluck('stock_deliveries.id')->all(), $total);
    }

    public function asController(StockDeliveryServiceInvoice $serviceInvoice, ActionRequest $request): StockDeliveryServiceInvoice
    {
        $this->serviceInvoice = $serviceInvoice;
        $this->initialisation($serviceInvoice->organisation, $request);

        return $this->handle($serviceInvoice, $this->validatedData);
    }

    public function action(StockDeliveryServiceInvoice $serviceInvoice, array $modelData): StockDeliveryServiceInvoice
    {
        $this->asAction       = true;
        $this->serviceInvoice = $serviceInvoice;
        $this->initialisation($serviceInvoice->organisation, $modelData);

        return $this->handle($serviceInvoice, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
