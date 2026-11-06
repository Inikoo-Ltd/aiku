<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 18 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\AgentPayment;

use App\Actions\Helpers\Media\SaveModelAttachment;
use App\Actions\OrgAction;
use App\Actions\SupplyChain\AgentInvoice\SyncAgentInvoiceChargesFromLedger;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderAttachmentScopeEnum;
use App\Enums\SupplyChain\AgentPayment\AgentPaymentTypeEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\AgentPayment;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * The agent writes a line of a client's ledger: a transfer the client sent, a refund from a supplier, or money it
 * spent from the client's funds. Amounts are in the agent's currency; a transfer also keeps what the client sent in
 * its own currency.
 */
class StoreAgentLedgerEntry extends OrgAction
{
    private Agent $agent;

    /**
     * @param  array{organisation_id: int, type: string, date: string, amount: float|string, purchase_order_id?: int|null, stock_delivery_id?: int|null, original_amount?: float|string|null, reference?: string|null, notes?: string|null, receipt?: UploadedFile|null}  $modelData
     */
    public function handle(Agent $agent, array $modelData): AgentPayment
    {
        $client        = Organisation::findOrFail($modelData['organisation_id']);
        $type          = AgentPaymentTypeEnum::from($modelData['type']);
        $purchaseOrder = ($purchaseOrderId = Arr::get($modelData, 'purchase_order_id')) ? PurchaseOrder::findOrFail($purchaseOrderId) : null;
        $stockDelivery = ($stockDeliveryId = Arr::get($modelData, 'stock_delivery_id')) ? StockDelivery::findOrFail($stockDeliveryId) : null;
        $originalAmount = $type === AgentPaymentTypeEnum::TRANSFER_RECEIVED ? Arr::get($modelData, 'original_amount') : null;

        $receipt = Arr::get($modelData, 'receipt');
        $media   = $receipt ? SaveModelAttachment::make()->action($purchaseOrder ?? $stockDelivery ?? $agent, [
            'path'         => $receipt->getPathName(),
            'originalName' => $receipt->getClientOriginalName(),
            'extension'    => $receipt->getClientOriginalExtension(),
            'scope'        => PurchaseOrderAttachmentScopeEnum::BANK_STATEMENT->value,
            'caption'      => pathinfo($receipt->getClientOriginalName(), PATHINFO_FILENAME),
        ]) : null;

        $entry = AgentPayment::create([
            'group_id'             => $agent->group_id,
            'agent_id'             => $agent->id,
            'organisation_id'      => $client->id,
            'type'                 => $type,
            'date'                 => $modelData['date'],
            'amount'               => round((float) $modelData['amount'], 2),
            'currency_id'          => $agent->organisation->currency_id,
            'purchase_order_id'    => $purchaseOrder?->id,
            'supplier_id'          => $purchaseOrder?->supplier_id,
            'stock_delivery_id'    => $stockDelivery?->id,
            'org_stock_id'         => $type === AgentPaymentTypeEnum::PACKAGING && $stockDelivery ? Arr::get($modelData, 'org_stock_id') : null,
            'original_amount'      => $originalAmount ? round((float) $originalAmount, 2) : null,
            'original_currency_id' => $originalAmount ? $client->currency_id : null,
            'reference'            => Arr::get($modelData, 'reference'),
            'notes'                => Arr::get($modelData, 'notes'),
            'media_id'             => $media?->id,
            'created_by'           => $this->asAction ? null : request()->user()?->id,
        ]);

        if ($stockDelivery) {
            SyncAgentInvoiceChargesFromLedger::run($stockDelivery);
        }

        return $entry;
    }

    public function rules(): array
    {
        return [
            'organisation_id'   => ['required', 'integer', Rule::exists('org_agents', 'organisation_id')->where('agent_id', $this->agent->id)],
            'type'              => ['required', Rule::enum(AgentPaymentTypeEnum::class)],
            'date'              => ['required', 'date', 'before_or_equal:today'],
            'amount'            => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'purchase_order_id' => ['nullable', 'integer', 'required_if:type,'.AgentPaymentTypeEnum::SUPPLIER_PAYMENT->value.','.AgentPaymentTypeEnum::SUPPLIER_DEPOSIT->value.','.AgentPaymentTypeEnum::SUPPLIER_BALANCE->value],
            'stock_delivery_id' => ['nullable', 'integer'],
            'org_stock_id'      => ['nullable', 'integer'],
            'original_amount'   => ['nullable', 'numeric', 'gt:0', 'max:999999999999'],
            'reference'         => ['nullable', 'string', 'max:255'],
            'notes'             => ['nullable', 'string', 'max:2000'],
            'receipt'           => ['nullable', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,webp,heic'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        $organisationId = (int) $this->get('organisation_id');

        if ($purchaseOrderId = $this->get('purchase_order_id')) {
            $belongs = PurchaseOrder::whereKey($purchaseOrderId)->where('agent_id', $this->agent->id)->where('organisation_id', $organisationId)->exists();
            if (!$belongs) {
                $validator->errors()->add('purchase_order_id', __('Choose an order this client placed through you.'));
            }
        }

        if (!self::orgStockOfContainer($this->get('org_stock_id') ? (int) $this->get('org_stock_id') : null, $this->get('stock_delivery_id') ? (int) $this->get('stock_delivery_id') : null)) {
            $validator->errors()->add('org_stock_id', __('Choose a SKO on the container.'));
        }

        if ($stockDeliveryId = $this->get('stock_delivery_id')) {
            $belongs = StockDelivery::whereKey($stockDeliveryId)->where('agent_id', $this->agent->id)->where('organisation_id', $organisationId)->exists();
            if (!$belongs) {
                $validator->errors()->add('stock_delivery_id', __('Choose a container of this client that comes through you.'));
            }

            $type = AgentPaymentTypeEnum::tryFrom((string) $this->get('type'));
            if ($belongs && $type?->isCharge() && !SyncAgentInvoiceChargesFromLedger::chargesOpen(StockDelivery::find($stockDeliveryId))) {
                $validator->errors()->add('stock_delivery_id', __('This container is already costed, its charges can no longer change.'));
            }
        }
    }

    public static function orgStockOfContainer(?int $orgStockId, ?int $stockDeliveryId): bool
    {
        if (!$orgStockId) {
            return true;
        }

        return $stockDeliveryId !== null && StockDeliveryItem::where('stock_delivery_id', $stockDeliveryId)
            ->where('org_stock_id', $orgStockId)
            ->where('state', '!=', StockDeliveryItemStateEnum::CANCELLED)
            ->exists();
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("procurement.{$this->agent->organisation_id}.edit");
    }

    public function asController(Agent $agent, ActionRequest $request): AgentPayment
    {
        $this->agent = $agent;
        $this->initialisation($agent->organisation, $request);

        return $this->handle($agent, $this->validatedData);
    }

    /**
     * @param  array{organisation_id: int, type: string, date: string, amount: float|string}  $modelData
     */
    public function action(Agent $agent, array $modelData): AgentPayment
    {
        $this->asAction = true;
        $this->agent    = $agent;
        $this->initialisation($agent->organisation, $modelData);

        return $this->handle($agent, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
