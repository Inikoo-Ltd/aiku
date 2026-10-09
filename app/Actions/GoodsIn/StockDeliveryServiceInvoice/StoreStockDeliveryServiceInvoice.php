<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDeliveryServiceInvoice;

use App\Actions\OrgAction;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryServiceInvoiceTypeEnum;
use App\Models\GoodsIn\StockDeliveryServiceInvoice;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class StoreStockDeliveryServiceInvoice extends OrgAction
{
    use WithStockDeliveryServiceInvoiceAllocations;

    public function handle(Organisation $organisation, array $modelData): StockDeliveryServiceInvoice
    {
        $exchange = $this->orgExchange((int) $modelData['currency_id'], Arr::get($modelData, 'exchange'));
        $total    = round((float) $modelData['total_amount'], 2);

        $serviceInvoice = DB::transaction(function () use ($organisation, $modelData, $exchange, $total) {
            /** @var StockDeliveryServiceInvoice $serviceInvoice */
            $serviceInvoice = StockDeliveryServiceInvoice::create(array_merge(
                Arr::only($modelData, ['type', 'issuer', 'reference', 'date', 'currency_id', 'paid_at', 'notes']),
                [
                    'group_id'         => $organisation->group_id,
                    'organisation_id'  => $organisation->id,
                    'exchange'         => $exchange,
                    'total_amount'     => $total,
                    'org_total_amount' => round($total * $exchange, 2),
                ]
            ));

            $serviceInvoice->stockDeliveries()->sync(self::splitAllocations($modelData['allocations'], $total));
            $this->syncStockDeliveries(array_column($modelData['allocations'], 'stock_delivery_id'));

            return $serviceInvoice;
        });

        $this->saveAttachments($serviceInvoice, $modelData);

        return $serviceInvoice;
    }

    public function rules(): array
    {
        return array_merge($this->allocationRules(), [
            'type'         => ['required', Rule::enum(StockDeliveryServiceInvoiceTypeEnum::class)],
            'issuer'       => ['required', 'string', 'max:255'],
            'reference'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'date'         => ['required', 'date'],
            'currency_id'  => ['required', 'exists:currencies,id'],
            'exchange'     => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'total_amount' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'paid_at'      => ['sometimes', 'nullable', 'date'],
            'notes'        => ['sometimes', 'nullable', 'string', 'max:5000'],
            'allocations'  => ['required', 'array', 'min:1', 'max:50'],
        ]);
    }

    public function afterValidator(Validator $validator): void
    {
        $this->validateAllocations($validator, total: is_numeric($this->get('total_amount')) ? (float) $this->get('total_amount') : null);
    }

    public function asController(Organisation $organisation, ActionRequest $request): StockDeliveryServiceInvoice
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, $this->validatedData);
    }

    public function action(Organisation $organisation, array $modelData): StockDeliveryServiceInvoice
    {
        $this->asAction = true;
        $this->initialisation($organisation, $modelData);

        return $this->handle($organisation, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
