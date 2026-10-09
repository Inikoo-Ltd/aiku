<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\AgentInvoice;

use App\Actions\OrgAction;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\AgentInvoice;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * Makes, or makes again, the invoice an agent issues for a container, from the container's lines as they stand.
 * It can be made again while the container is still at the agent, and keeps its number; once the container is
 * dispatched the invoice is what was invoiced and does not change.
 */
class StoreAgentInvoice extends OrgAction
{
    use WithAgentOrganisation;

    public const array OPEN_STATES = [
        StockDeliveryStateEnum::IN_PROCESS,
        StockDeliveryStateEnum::CONFIRMED,
        StockDeliveryStateEnum::READY_TO_SHIP,
    ];

    /**
     * @throws ValidationException
     */
    public function handle(Agent $agent, StockDelivery $stockDelivery): AgentInvoice
    {
        if ($stockDelivery->agent_id !== $agent->id) {
            throw ValidationException::withMessages(['invoice' => __('This container is not one of this agent.')]);
        }

        if (!in_array($stockDelivery->state, self::OPEN_STATES, true)) {
            throw ValidationException::withMessages(['invoice' => __('The container has left the agent, its invoice can no longer change.')]);
        }

        $lines = $stockDelivery->items()
            ->where('state', '!=', StockDeliveryItemStateEnum::CANCELLED)
            ->with('orgStock:id,code,name')
            ->orderBy('id')
            ->get()
            ->map(fn (StockDeliveryItem $item) => [
                'org_stock_id' => $item->org_stock_id,
                'code'         => $item->orgStock?->code,
                'name'         => $item->orgStock?->name,
                'quantity'     => (float) $item->unit_quantity,
                'unit_price'   => (float) $item->unit_quantity > 0 ? round((float) $item->net_amount / (float) $item->unit_quantity, 4) : 0.0,
                'amount'       => (float) $item->net_amount,
            ])
            ->values()
            ->all();

        if ($lines === []) {
            throw ValidationException::withMessages(['invoice' => __('The container has no lines to invoice.')]);
        }

        return DB::transaction(function () use ($agent, $stockDelivery, $lines) {
            Agent::whereKey($agent->id)->lockForUpdate()->first();

            $agentInvoice = $stockDelivery->agentInvoice()->first();

            $number = $agentInvoice?->number
                ?? ((int) AgentInvoice::where('agent_id', $agent->id)->max('number') + 1);

            $agentInvoice = AgentInvoice::updateOrCreate(
                ['stock_delivery_id' => $stockDelivery->id],
                [
                    'group_id'        => $agent->group_id,
                    'agent_id'        => $agent->id,
                    'organisation_id' => $stockDelivery->organisation_id,
                    'number'          => $number,
                    'reference'       => strtoupper($agent->organisation->code).'-INV-'.str_pad((string) $number, 5, '0', STR_PAD_LEFT),
                    'date'            => now()->toDateString(),
                    'currency_id'     => $stockDelivery->currency_id,
                    'number_lines'    => count($lines),
                    'goods_amount'    => $goodsAmount = round(array_sum(array_column($lines, 'amount')), 2),
                    'total_amount'    => round($goodsAmount + (float) ($agentInvoice?->charges_amount ?? 0), 2),
                    'lines'           => $lines,
                ]
            );

            $data = $stockDelivery->data ?? [];
            data_set($data, 'invoice_number', $agentInvoice->reference);
            data_set($data, 'invoice_date', $agentInvoice->date->toDateString());
            $stockDelivery->update(['data' => $data]);

            return $agentInvoice;
        });
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    /**
     * @throws ValidationException
     */
    public function asController(Organisation $organisation, StockDelivery $stockDelivery, ActionRequest $request): AgentInvoice
    {
        $this->initialisation($organisation, $request);
        $agent = $this->getOrganisationAgent($organisation);
        abort_unless($agent, 404);

        return $this->handle($agent, $stockDelivery);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
