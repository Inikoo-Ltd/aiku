<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\StockDeliveryInvoice;

use App\Actions\OrgAction;
use App\Actions\SupplyChain\AgentInvoice\StoreAgentInvoice;
use App\Actions\SupplyChain\AgentInvoice\UpdateAgentInvoiceCharges;
use App\Enums\SupplyChain\StockDeliveryInvoice\StockDeliveryInvoiceSourceEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\SupplyChain\AgentInvoice;
use App\Models\SupplyChain\SupplierInvoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * Records the paper invoice the agent or supplier sent for a delivery, in place of the estimate (or of nothing).
 * An invoice the agent made in aiku is the agent's own and is not overwritten from here. The estimate it replaces
 * is kept in the invoice's data, so what was estimated can still be compared with what was invoiced.
 */
class StoreActualStockDeliveryInvoice extends OrgAction
{
    /**
     * @throws ValidationException
     */
    public function handle(StockDelivery $stockDelivery, array $modelData): AgentInvoice|SupplierInvoice
    {
        $invoice = $stockDelivery->agent_id ? $stockDelivery->agentInvoice()->first() : $stockDelivery->supplierInvoice()->first();

        if ($invoice?->source === StockDeliveryInvoiceSourceEnum::AGENT) {
            throw ValidationException::withMessages(['invoice' => __('The agent made this invoice in aiku, it can not be replaced here.')]);
        }

        $charges = collect(Arr::get($modelData, 'charges', []))
            ->map(fn (array $charge) => [
                'description' => trim($charge['description']),
                'type'        => $charge['type'] ?? UpdateAgentInvoiceCharges::CHARGE_OTHER,
                'amount'      => round((float) $charge['amount'], 2),
            ])
            ->values()
            ->all();

        $lines         = $invoice?->lines ?: StoreAgentInvoice::invoiceLines($stockDelivery);
        $goodsAmount   = round((float) $modelData['goods_amount'], 2);
        $chargesAmount = round(array_sum(array_column($charges, 'amount')), 2);

        $invoiceData = [
            'group_id'          => $stockDelivery->group_id,
            'organisation_id'   => $stockDelivery->organisation_id,
            'stock_delivery_id' => $stockDelivery->id,
            'source'            => StockDeliveryInvoiceSourceEnum::ACTUAL,
            'reference'         => trim($modelData['reference']),
            'date'              => $modelData['date'],
            'currency_id'       => $stockDelivery->currency_id,
            'number_lines'      => count($lines),
            'goods_amount'      => $goodsAmount,
            'charges_amount'    => $chargesAmount,
            'total_amount'      => round($goodsAmount + $chargesAmount, 2),
            'lines'             => $lines,
            'charges'           => $charges,
        ];

        if ($invoice?->source === StockDeliveryInvoiceSourceEnum::ESTIMATED) {
            $invoiceData['data'] = ['estimate' => Arr::only($invoice->toArray(), ['reference', 'date', 'goods_amount', 'charges_amount', 'total_amount', 'charges'])];
        }

        if ($stockDelivery->agent_id) {
            $invoice = AgentInvoice::updateOrCreate(['stock_delivery_id' => $stockDelivery->id], $invoiceData + ['agent_id' => $stockDelivery->agent_id]);
        } else {
            $invoice = SupplierInvoice::updateOrCreate(['stock_delivery_id' => $stockDelivery->id], $invoiceData + ['supplier_id' => $stockDelivery->supplier_id]);
        }

        $data = $stockDelivery->data ?? [];
        data_set($data, 'invoice_number', $invoice->reference);
        data_set($data, 'invoice_date', $invoice->date->toDateString());
        $stockDelivery->update(['data' => $data]);

        return $invoice;
    }

    public function rules(): array
    {
        return [
            'reference'             => ['required', 'string', 'max:255'],
            'date'                  => ['required', 'date'],
            'goods_amount'          => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'charges'               => ['present', 'array', 'max:50'],
            'charges.*.description' => ['required', 'string', 'max:255'],
            'charges.*.type'        => ['sometimes', 'string', 'in:'.UpdateAgentInvoiceCharges::CHARGE_FREIGHT.','.UpdateAgentInvoiceCharges::CHARGE_OTHER],
            'charges.*.amount'      => ['required', 'numeric', 'min:0', 'max:999999999999'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    /**
     * @throws ValidationException
     */
    public function asController(StockDelivery $stockDelivery, ActionRequest $request): AgentInvoice|SupplierInvoice
    {
        $this->initialisation($stockDelivery->organisation, $request);

        return $this->handle($stockDelivery, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
