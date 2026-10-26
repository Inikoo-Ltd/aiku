<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-10-16 14:55
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\AgentGoodsReceipt;

use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\MarkPurchaseOrderStage;
use App\Actions\Procurement\AgentWarehouseStock\ImportAgentWarehouseStocksFromContainerList;
use App\Actions\Procurement\AgentWarehouseStock\UI\IndexAgentProductsInProduction;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Enums\Procurement\AgentGoodsReceipt\AgentGoodsReceiptStateEnum;
use App\Enums\Procurement\AgentWarehouseStock\AgentWarehouseStockStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Models\Procurement\AgentGoodsReceipt;
use App\Models\Procurement\AgentWarehouseStock;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SupplyChain\Agent;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * Records one delivery the agent received from a supplier as a goods receipt, booked against the purchase order lines
 * still in production: each line becomes warehouse stock, received and not checked yet, and leaves the In production
 * page once received in full. The signed delivery note can be photographed with it. Goods arriving show the supplier
 * started making the order, so its production start is set when it was not yet, and once every line of the order is
 * received in full the supplier has finished making it, so its production done is set too.
 */
class StoreAgentGoodsReceipt extends OrgAction
{
    use WithAgentOrganisation;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    /**
     * @param  array{lines: list<array{purchase_order_transaction_id: int, quantity: float}>, received_at?: string, supplier_delivery_note?: ?string, notes?: ?string, attachments?: array}  $modelData
     */
    public function handle(Agent $agent, array $modelData, ?User $user = null): AgentGoodsReceipt
    {
        $quantities = collect($modelData['lines'])
            ->groupBy('purchase_order_transaction_id')
            ->map(fn ($group) => round($group->sum('quantity'), 3));

        $receivedAt = Arr::get($modelData, 'received_at') ?: now()->toDateString();

        $receipt = DB::transaction(function () use ($agent, $modelData, $quantities, $user, $receivedAt) {
            $purchaseOrders = PurchaseOrder::whereIn('id', PurchaseOrderTransaction::whereIn('id', $quantities->keys())->select('purchase_order_id'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            PurchaseOrderTransaction::whereIn('id', $quantities->keys())->orderBy('id')->lockForUpdate()->pluck('id');

            $inProduction = IndexAgentProductsInProduction::inProductionLines($agent)
                ->join('supplier_products', 'supplier_products.id', 'purchase_order_transactions.supplier_product_id')
                ->whereIn('purchase_order_transactions.id', $quantities->keys())
                ->select([
                    'purchase_order_transactions.id',
                    'purchase_order_transactions.organisation_id',
                    'purchase_order_transactions.supplier_product_id',
                    'purchase_order_transactions.purchase_order_id',
                    'supplier_products.supplier_id',
                    DB::raw(IndexAgentProductsInProduction::remainingQuantitySql($agent).' as remaining'),
                ])
                ->get()
                ->keyBy('id');

            $errors = [];
            foreach ($quantities as $transactionId => $quantity) {
                $line = $inProduction->get($transactionId);
                if ($quantity <= 0) {
                    $errors["lines.$transactionId"] = __('Enter how many units were received');
                } elseif (!$line) {
                    $errors["lines.$transactionId"] = __('This line is no longer in production');
                } elseif ($quantity > (float) $line->remaining) {
                    $errors["lines.$transactionId"] = __('Only :quantity units of this line are still in production', ['quantity' => (float) $line->remaining]);
                }
            }
            if ($inProduction->pluck('supplier_id')->unique()->count() > 1) {
                $errors['lines'] = __('A goods receipt holds what one supplier delivered, receive each supplier separately');
            }
            if ($errors) {
                throw ValidationException::withMessages($errors);
            }

            DB::table('agents')->where('id', $agent->id)->lockForUpdate()->value('id');
            $number = (int) AgentGoodsReceipt::where('agent_id', $agent->id)->max('number') + 1;

            $receipt = AgentGoodsReceipt::create([
                'group_id'               => $agent->group_id,
                'agent_id'               => $agent->id,
                'supplier_id'            => $inProduction->first()->supplier_id,
                'number'                 => $number,
                'reference'              => sprintf('GR-%05d', $number),
                'state'                  => AgentGoodsReceiptStateEnum::RECEIVED,
                'received_at'            => $receivedAt,
                'user_id'                => $user?->id,
                'supplier_delivery_note' => Arr::get($modelData, 'supplier_delivery_note'),
                'notes'                  => Arr::get($modelData, 'notes'),
                'number_items'           => $quantities->count(),
                'quantity'               => $quantities->sum(),
            ]);

            foreach ($quantities as $transactionId => $quantity) {
                $line = $inProduction[$transactionId];

                $stock = AgentWarehouseStock::create([
                    'group_id'                      => $agent->group_id,
                    'agent_id'                      => $agent->id,
                    'organisation_id'               => $line->organisation_id,
                    'supplier_product_id'           => $line->supplier_product_id,
                    'purchase_order_id'             => $line->purchase_order_id,
                    'purchase_order_transaction_id' => $transactionId,
                    'agent_goods_receipt_id'        => $receipt->id,
                    'state'                         => AgentWarehouseStockStateEnum::RECEIVED,
                    'quantity'                      => $quantity,
                    'notes'                         => $receipt->reference,
                ]);

                $receipt->items()->create([
                    'group_id'                      => $agent->group_id,
                    'organisation_id'               => $line->organisation_id,
                    'purchase_order_id'             => $line->purchase_order_id,
                    'purchase_order_transaction_id' => $transactionId,
                    'supplier_product_id'           => $line->supplier_product_id,
                    'agent_warehouse_stock_id'      => $stock->id,
                    'quantity'                      => $quantity,
                ]);
            }

            $purchaseOrders->whereIn('id', $inProduction->pluck('purchase_order_id')->unique())
                ->whereNull('production_started_at')
                ->each(fn (PurchaseOrder $purchaseOrder) => ImportAgentWarehouseStocksFromContainerList::make()->markInProduction(
                    $purchaseOrder,
                    __('Goods receipt :reference: goods for this order arrived at the agent warehouse', ['reference' => $receipt->reference]),
                    Carbon::parse($receivedAt)->toDateString()
                ));

            $purchaseOrders->whereIn('id', $inProduction->pluck('purchase_order_id')->unique())
                ->whereNull('produced_at')
                ->filter(fn (PurchaseOrder $purchaseOrder) => $this->isReceivedInFull($purchaseOrder))
                ->each(fn (PurchaseOrder $purchaseOrder) => MarkPurchaseOrderStage::make()->action($purchaseOrder, [
                    'field'  => 'produced_at',
                    'date'   => Carbon::parse($receivedAt)->toDateString(),
                    'reason' => __('Goods receipt :reference: all goods of this order arrived at the agent warehouse', ['reference' => $receipt->reference]),
                ]));

            return $receipt;
        });

        UploadAgentGoodsReceiptAttachments::make()->saveFiles($receipt, Arr::get($modelData, 'attachments', []));

        return $receipt;
    }

    public function isReceivedInFull(PurchaseOrder $purchaseOrder): bool
    {
        return $purchaseOrder->purchaseOrderTransactions()
            ->leftJoinSub(IndexAgentProductsInProduction::receivedLines(), 'received_lines', 'received_lines.purchase_order_transaction_id', 'purchase_order_transactions.id')
            ->whereNotNull('purchase_order_transactions.supplier_product_id')
            ->whereIn('purchase_order_transactions.state', [PurchaseOrderTransactionStateEnum::SUBMITTED, PurchaseOrderTransactionStateEnum::CONFIRMED])
            ->whereRaw('purchase_order_transactions.quantity_ordered > coalesce(received_lines.quantity_accepted, 0)')
            ->doesntExist();
    }

    public function rules(): array
    {
        return [
            'lines'                                 => ['required', 'array', 'min:1'],
            'lines.*.purchase_order_transaction_id' => ['required', 'integer'],
            'lines.*.quantity'                      => ['required', 'numeric', 'gt:0'],
            'received_at'                           => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'supplier_delivery_note'                => ['sometimes', 'nullable', 'string', 'max:64'],
            'notes'                                 => ['sometimes', 'nullable', 'string', 'max:500'],
            'attachments'                           => ['sometimes', 'array', 'max:10'],
            'attachments.*'                         => ['file', 'mimes:jpg,jpeg,png,webp,heic,heif,pdf', 'max:20000'],
        ];
    }

    public function asController(Organisation $organisation, ActionRequest $request): AgentGoodsReceipt
    {
        $agent = $this->getOrganisationAgent($organisation);
        abort_unless($agent, 404);
        $this->initialisation($organisation, $request);

        return $this->handle($agent, $this->validatedData, $request->user());
    }

    public function action(Agent $agent, array $modelData, ?User $user = null): AgentGoodsReceipt
    {
        $this->asAction = true;
        $this->initialisation($agent->organisation, $modelData);

        return $this->handle($agent, $this->validatedData, $user);
    }

    public function htmlResponse(AgentGoodsReceipt $receipt): RedirectResponse
    {
        return Redirect::back()->with('notification', [
            'status'  => 'success',
            'title'   => __('Goods receipt :reference', ['reference' => $receipt->reference]),
            'message' => __(':lines lines, :units units moved to Received', ['lines' => $receipt->number_items, 'units' => (float) $receipt->quantity]),
        ]);
    }
}
