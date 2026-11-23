<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery\UI;

use App\Actions\Procurement\PurchaseOrder\SyncPurchaseOrderDepositPaid;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryCostTypeEnum;
use App\Enums\SupplyChain\AspoDeposit\DepositRequestItemKindEnum;
use App\Enums\SupplyChain\AspoDeposit\DepositRequestStateEnum;
use App\Models\Helpers\Currency;
use App\Models\SupplyChain\DepositRequestItem;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryServiceInvoiceTypeEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderAttachmentScopeEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryCost;
use App\Models\GoodsIn\StockDeliveryServiceInvoice;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderDepositPayment;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierPayment;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The import file of a shipment, the paper checklist procurement keeps per container (HELP-4543): each step is
 * ticked from what aiku already records (purchase orders, files by type, payments, the costing checklist, the
 * forwarder and customs bills) and a person can mark it done, pending or N/A by hand, which wins over aiku.
 * Since HELP-4761 this tab is the one place to book costs: the invoice, shipping and customs steps open the costing
 * dialogs, which read every document uploaded there: the invoice and shipping steps lose their plain upload wherever
 * the costing is open to the user, the customs declaration keeps it (it is not a bill the costing reads).
 */
class GetStockDeliveryImportFile
{
    use AsObject;

    public const array STEPS = [
        'purchase_order',
        'proforma',
        'deposit',
        'shipping_quotes',
        'commercial_invoice',
        'packing_list',
        'bill_of_lading',
        'balance_payment',
        'original_documents',
        'shipping_invoice',
        'duty_invoice',
        'duty_paid',
        'duty_rates',
    ];

    public const array STATUSES = ['done', 'pending', 'na'];

    public const array COSTING_SECTIONS = [
        'commercial_invoice' => 'invoice',
        'shipping_invoice'   => 'landed',
        'duty_invoice'       => 'landed',
    ];

    public const array UPLOADED_IN_COSTING = ['commercial_invoice', 'shipping_invoice'];

    private const array DUTY_BILL_TYPES = [
        StockDeliveryServiceInvoiceTypeEnum::DUTY,
        StockDeliveryServiceInvoiceTypeEnum::ANTI_DUMPING,
        StockDeliveryServiceInvoiceTypeEnum::IMPORT_LEVY,
        StockDeliveryServiceInvoiceTypeEnum::TAX,
    ];

    public static function labels(): array
    {
        return [
            'purchase_order'     => __('Purchase order'),
            'proforma'           => __('Pro-forma invoice'),
            'deposit'            => __('Deposit payment'),
            'shipping_quotes'    => __('Shipping quotes'),
            'commercial_invoice' => __('Commercial invoice'),
            'packing_list'       => __('Packing list'),
            'bill_of_lading'     => __('Bill of lading / CMR / T1'),
            'balance_payment'    => __('Balance payment'),
            'original_documents' => __('Original documents received / sent'),
            'shipping_invoice'   => __('Shipping invoice'),
            'duty_invoice'       => __('Customs declaration: duty classification and tariff codes'),
            'duty_paid'          => __('Duty payment'),
            'duty_rates'         => __('Duty rates verified'),
        ];
    }

    public function handle(StockDelivery $stockDelivery, bool $canEdit = false, bool $canUpload = false): array
    {
        $purchaseOrders = $stockDelivery->purchaseOrders()->orderBy('purchase_orders.date')->get();
        $files          = IndexStockDeliveryAttachmentsJson::make()->handle($stockDelivery);
        $marks          = Arr::get($stockDelivery->data, 'import_file', []);
        $auto           = $this->auto($stockDelivery, $purchaseOrders, $files);
        $userNames      = User::whereIn('id', collect($marks)->pluck('user_id')->filter()->unique())->pluck('contact_name', 'id');
        $mode           = $this->mode($stockDelivery);
        $money          = $this->money($stockDelivery, $purchaseOrders);

        $steps = [];
        foreach (self::labels() as $key => $label) {
            $mark    = Arr::get($marks, $key) ?? ($key === 'packing_list' ? Arr::get($marks, 'commercial_invoice') : null);
            $default = $auto[$key]['status'] ?? null;
            if (!$default && $mode === 'road' && in_array($key, ['bill_of_lading', 'original_documents'], true)) {
                $default = 'na';
            }

            $steps[] = [
                'key'      => $key,
                'label'    => $label,
                'status'   => $mark['status'] ?? $default ?? 'pending',
                'date'     => $mark ? ($mark['date'] ?? null) : ($auto[$key]['date'] ?? null),
                'source'   => $auto[$key]['source'] ?? null,
                'is_auto'  => !$mark && $default !== null,
                'marked'   => $mark ? [
                    'by' => $userNames[$mark['user_id'] ?? 0] ?? null,
                    'at' => $mark['at'] ?? null,
                ] : null,
                'scope'    => $this->scope($key)?->value,
                'costing'  => self::COSTING_SECTIONS[$key] ?? null,
                'upload_in_costing' => in_array($key, self::UPLOADED_IN_COSTING, true),
                'files'    => $this->scope($key) !== null ? $files->where('scope', $this->scope($key)->value)->values()->all() : [],
                'money'    => $money[$key] ?? null,
            ];
        }

        $orderedAt   = $purchaseOrders->map(fn (PurchaseOrder $purchaseOrder) => $purchaseOrder->submitted_at ?? $purchaseOrder->date)->filter()->min();
        $deliveredAt = in_array($stockDelivery->state, StockDelivery::STATES_RECEIVED, true) ? ($stockDelivery->received_at ?? $stockDelivery->arrived_at) : $stockDelivery->arrived_at;
        $outstanding = collect($steps)->where('status', 'pending')->pluck('label')->values()->all();
        $costsMissing = $stockDelivery->costsMissing();

        $checks = [
            'goods_received' => in_array($stockDelivery->state, StockDelivery::STATES_RECEIVED, true),
            'costed'         => (bool) $stockDelivery->is_costed,
            'documents'      => $outstanding === [],
        ];

        return [
            'summary'       => [
                'purchase_orders' => $purchaseOrders->map(fn (PurchaseOrder $purchaseOrder) => [
                    'reference' => $purchaseOrder->reference,
                    'route'     => [
                        'name'       => 'grp.org.procurement.purchase_orders.show',
                        'parameters' => [$stockDelivery->organisation->slug, $purchaseOrder->slug],
                    ],
                ])->values()->all(),
                'supplier'        => $stockDelivery->parent_type === 'OrgAgent' ? null : $stockDelivery->parent_name,
                'suppliers'       => Supplier::whereIn('id', $purchaseOrders->pluck('supplier_id')->filter()->unique())->orderBy('name')->pluck('name')->all(),
                'agent'           => $stockDelivery->parent_type === 'OrgAgent' ? $stockDelivery->parent_name : null,
                'ordered_at'      => $orderedAt ? Carbon::parse($orderedAt)->toDateString() : null,
                'incoterm'        => Arr::get($stockDelivery->data, 'incoterm'),
                'mode'            => $mode,
                'eta'             => Arr::get($stockDelivery->data, 'estimated_receiving_date'),
                'delivered_at'    => $deliveredAt?->toDateString(),
                'days'            => $orderedAt && $deliveredAt ? (int) Carbon::parse($orderedAt)->startOfDay()->diffInDays($deliveredAt->copy()->startOfDay()) : null,
            ],
            'steps'         => $steps,
            'outstanding'   => $outstanding,
            'costs_missing' => array_values($costsMissing),
            'checks'        => $checks,
            'is_complete'   => !in_array(false, $checks, true),
            'can_edit'      => $canEdit,
            'can_upload'    => $canUpload,
            'update_route'  => [
                'method'     => 'patch',
                'name'       => 'grp.models.stock-delivery.import_file.update',
                'parameters' => ['stockDelivery' => $stockDelivery->id],
            ],
            'detach_route'  => [
                'method'     => 'delete',
                'name'       => 'grp.models.stock-delivery.attachment.detach',
                'parameters' => ['stockDelivery' => $stockDelivery->id],
            ],
            'documents_count' => $files->count(),
            'documents_route' => [
                'name'       => 'grp.models.stock-delivery.import_file.documents',
                'parameters' => ['stockDelivery' => $stockDelivery->id],
            ],
            'attach_route'  => [
                'method'     => 'post',
                'name'       => 'grp.models.stock-delivery.attachment.attach',
                'parameters' => ['stockDelivery' => $stockDelivery->id],
            ],
        ];
    }

    /**
     * The deposits sent for the orders whose first delivery this is: supplier instalments with their bank statement and
     * the advance lines of live money requests (paid or only requested). AgentInvoice::advancePayments reads the paid
     * ones too, so the Import file and the agent payments dialog agree (HELP-4794).
     *
     * @return Collection<int, array{purchase_order: string, date: ?string, amount: float, currency: ?string, original_amount: ?float, original_currency: ?string, percent: ?float, is_paid: bool, bank_statement: ?string}>
     */
    public static function sentDeposits(StockDelivery $stockDelivery, ?Collection $purchaseOrders = null): Collection
    {
        $purchaseOrders ??= $stockDelivery->purchaseOrders()->get();
        $firstDeliveryOrders = $purchaseOrders->filter(
            fn (PurchaseOrder $purchaseOrder) => (int) $purchaseOrder->stockDeliveries()->min('stock_deliveries.id') === $stockDelivery->id
        )->keyBy('id');
        $orderCurrencies     = Currency::whereIn('id', $purchaseOrders->pluck('currency_id')->unique())->pluck('code', 'id');
        $percent             = fn (PurchaseOrder $purchaseOrder, float $amount): ?float => SyncPurchaseOrderDepositPaid::totalToPay($purchaseOrder) > 0
            ? round(100 * $amount / SyncPurchaseOrderDepositPaid::totalToPay($purchaseOrder), 1)
            : null;

        $rows = PurchaseOrderDepositPayment::whereIn('purchase_order_id', $firstDeliveryOrders->keys())
            ->with(['bankStatement:id,ulid', 'originalCurrency:id,code'])
            ->orderBy('date')
            ->orderBy('id')
            ->get()
            ->map(function (PurchaseOrderDepositPayment $payment) use ($firstDeliveryOrders, $orderCurrencies, $percent) {
                $purchaseOrder = $firstDeliveryOrders[$payment->purchase_order_id];

                return [
                    'purchase_order'    => $purchaseOrder->reference,
                    'date'              => $payment->date->toDateString(),
                    'amount'            => (float) $payment->amount,
                    'currency'          => $orderCurrencies[$purchaseOrder->currency_id] ?? null,
                    'original_amount'   => $payment->original_amount !== null ? (float) $payment->original_amount : null,
                    'original_currency' => $payment->originalCurrency?->code,
                    'percent'           => $percent($purchaseOrder, (float) $payment->amount),
                    'is_paid'           => true,
                    'bank_statement'    => $payment->bankStatement?->ulid,
                ];
            });

        $advances = DepositRequestItem::where('kind', DepositRequestItemKindEnum::ADVANCE)
            ->whereIn('purchase_order_id', $firstDeliveryOrders->keys())
            ->whereHas('depositRequest', fn ($query) => $query->whereIn('state', [DepositRequestStateEnum::REQUESTED, DepositRequestStateEnum::SETTLED]))
            ->with(['depositRequest.currency:id,code', 'depositRequest.transfers' => fn ($query) => $query->whereNotNull('media_id')->with('bankStatement:id,ulid')])
            ->orderBy('id')
            ->get()
            ->map(function (DepositRequestItem $item) use ($firstDeliveryOrders, $percent) {
                $purchaseOrder = $firstDeliveryOrders[$item->purchase_order_id];
                $transfer      = $item->depositRequest->transfers->first();

                return [
                    'purchase_order'    => $purchaseOrder->reference,
                    'date'              => ($item->paid_at ?? $item->depositRequest->requested_at)?->toDateString(),
                    'amount'            => (float) $item->amount,
                    'currency'          => $item->depositRequest->currency->code,
                    'original_amount'   => null,
                    'original_currency' => null,
                    'percent'           => $item->depositRequest->currency_id === $purchaseOrder->currency_id ? $percent($purchaseOrder, (float) $item->amount) : null,
                    'is_paid'           => $item->paid_at !== null,
                    'bank_statement'    => $transfer?->bankStatement?->ulid,
                ];
            });

        return $rows->concat($advances)->sortBy('date')->values();
    }

    /**
     * HELP-4794: what was sent as deposit and what is left to pay. A supplier order's deposit is its instalments
     * (purchase_order_deposit_payments, in the order's currency, with the bank statement); an agent order's is the
     * advance lines of the money requests, with the bank statement of the transfer that paid them. A deposit counts
     * on its order's first delivery only, as the supplier payments dialog does. The % is of the order's goods
     * (SyncPurchaseOrderDepositPaid::totalToPay), only where the money is in the order's currency. Left to pay is
     * the invoice total, or the orders' total until the invoice arrives, less the deposits and the payments on this
     * delivery, and only when everything is in one currency.
     *
     * @return array{deposit: array<string, mixed>, balance_payment: array<string, mixed>}
     */
    private function money(StockDelivery $stockDelivery, Collection $purchaseOrders): array
    {
        $orderCurrencies = Currency::whereIn('id', $purchaseOrders->pluck('currency_id')->unique())->pluck('code', 'id');
        $deposits        = self::sentDeposits($stockDelivery, $purchaseOrders);
        $paidRows        = $deposits->where('is_paid', true);
        $invoice         = $stockDelivery->agentInvoice ?? $stockDelivery->supplierInvoice;
        $payments        = $stockDelivery->agent_id ? $stockDelivery->agentPayments()->get() : $stockDelivery->supplierPayments()->get();
        $scheduled       = $payments->filter(fn ($payment) => $payment instanceof SupplierPayment && $payment->isScheduled());
        $payments        = $payments->diffKeys($scheduled);
        $currencies      = $paidRows->pluck('currency')
            ->concat(Currency::whereIn('id', $payments->pluck('currency_id')->unique())->pluck('code'))
            ->push($invoice ? $invoice->currency->code : null)
            ->concat($invoice ? [] : $purchaseOrders->map(fn (PurchaseOrder $purchaseOrder) => $orderCurrencies[$purchaseOrder->currency_id] ?? null))
            ->filter()
            ->unique();
        $total           = $invoice ? (float) $invoice->total_amount : round($purchaseOrders->sum(fn (PurchaseOrder $purchaseOrder) => SyncPurchaseOrderDepositPaid::totalToPay($purchaseOrder)), 2);
        $sent            = round((float) $paidRows->sum('amount'), 2);
        $paid            = round((float) $payments->sum('amount') + ($stockDelivery->agent_id ? (float) $stockDelivery->depositApplications()->sum('amount') : 0), 2);

        return [
            'deposit'         => [
                'rows'     => $deposits->all(),
                'currency' => $paidRows->pluck('currency')->unique()->count() === 1 ? $paidRows->first()['currency'] : null,
                'total'    => $paidRows->pluck('currency')->unique()->count() === 1 ? $sent : null,
            ],
            'balance_payment' => $currencies->count() === 1 && $total > 0 ? [
                'currency'     => $currencies->first(),
                'total'        => $total,
                'total_source' => $invoice ? 'invoice' : 'orders',
                'deposits'     => $sent,
                'payments'     => $paid,
                'left'         => round($total - $sent - $paid, 2),
                'scheduled'    => $scheduled->isEmpty() ? null : [
                    'amount' => round((float) $scheduled->sum('amount'), 2),
                    'date'   => $scheduled->min('date')->toDateString(),
                ],
            ] : null,
        ];
    }

    /**
     * sea for container and LCL, air, road for parcels and lorries; production and partner deliveries have no mode.
     */
    private function mode(StockDelivery $stockDelivery): ?string
    {
        return match (Arr::get($stockDelivery->data, 'delivery_type')) {
            'container', 'lcl' => 'sea',
            'air'              => 'air',
            'parcel'           => 'road',
            default            => null,
        };
    }

    public static function scope(string $key): ?PurchaseOrderAttachmentScopeEnum
    {
        return match ($key) {
            'proforma'           => PurchaseOrderAttachmentScopeEnum::PROFORMA,
            'deposit'            => PurchaseOrderAttachmentScopeEnum::DEPOSIT_PAYMENT,
            'balance_payment'    => PurchaseOrderAttachmentScopeEnum::BANK_STATEMENT,
            'duty_paid'          => PurchaseOrderAttachmentScopeEnum::DUTY_PAYMENT,
            'shipping_quotes'    => PurchaseOrderAttachmentScopeEnum::SHIPPING_QUOTE,
            'commercial_invoice' => PurchaseOrderAttachmentScopeEnum::INVOICE,
            'packing_list'       => PurchaseOrderAttachmentScopeEnum::PACKING_LIST,
            'bill_of_lading'     => PurchaseOrderAttachmentScopeEnum::BILL_OF_LADING,
            'original_documents' => PurchaseOrderAttachmentScopeEnum::OTHER,
            'shipping_invoice'   => PurchaseOrderAttachmentScopeEnum::SERVICE_INVOICE,
            'duty_invoice'       => PurchaseOrderAttachmentScopeEnum::CUSTOMS,
            default              => null,
        };
    }

    /**
     * @return array<string, array{status: string, date: ?string, source: string}>
     */
    private function auto(StockDelivery $stockDelivery, Collection $purchaseOrders, Collection $files): array
    {
        $date = fn ($value): ?string => $value ? Carbon::parse($value)->toDateString() : null;
        $done = fn ($value, string $source): array => ['status' => 'done', 'date' => $date($value), 'source' => $source];
        $auto = [];

        if ($purchaseOrders->isNotEmpty()) {
            $auto['purchase_order'] = $done($purchaseOrders->map(fn (PurchaseOrder $purchaseOrder) => $purchaseOrder->submitted_at ?? $purchaseOrder->date)->filter()->min(), __('Linked purchase orders'));
        }

        $poProforma = DB::table('model_has_attachments')
            ->where('model_type', 'PurchaseOrder')
            ->whereIn('model_id', $purchaseOrders->pluck('id'))
            ->where('scope', PurchaseOrderAttachmentScopeEnum::PROFORMA->value)
            ->min('created_at');
        if ($poProforma || $files->where('scope', PurchaseOrderAttachmentScopeEnum::PROFORMA->value)->isNotEmpty()) {
            $auto['proforma'] = $done($poProforma, __('Pro-forma file attached'));
        }

        $depositAt = PurchaseOrderDepositPayment::whereIn('purchase_order_id', $purchaseOrders->pluck('id'))->min('date')
            ?? $purchaseOrders->pluck('deposit_paid_at')->filter()->min();
        if ($depositAt || $stockDelivery->depositApplications()->exists()) {
            $auto['deposit'] = $done($depositAt, __('Deposit recorded on the purchase order'));
        }

        if ($files->where('scope', PurchaseOrderAttachmentScopeEnum::SHIPPING_QUOTE->value)->isNotEmpty()) {
            $auto['shipping_quotes'] = $done(null, __('Shipping quote attached'));
        }

        if ($stockDelivery->agentInvoice()->exists() || $stockDelivery->supplierInvoice()->exists()) {
            $auto['commercial_invoice'] = $done(Arr::get($stockDelivery->data, 'invoice_date'), __('Invoice on file'));
        } elseif ($files->where('scope', PurchaseOrderAttachmentScopeEnum::INVOICE->value)->isNotEmpty()) {
            $auto['commercial_invoice'] = $done(null, $stockDelivery->parent_type === 'OrgPartner' ? __('Invoice on file') : __('Invoice file attached, amounts not entered'));
        }

        if ($files->where('scope', PurchaseOrderAttachmentScopeEnum::PACKING_LIST->value)->isNotEmpty()) {
            $auto['packing_list'] = $done(null, __('Packing list attached'));
        }

        if ($files->where('scope', PurchaseOrderAttachmentScopeEnum::BILL_OF_LADING->value)->isNotEmpty()) {
            $auto['bill_of_lading'] = $done(null, __('Bill of lading attached'));
        }

        $balanceAt   = $stockDelivery->supplierPayments()->whereDate('date', '<=', today())->max('date') ?? $stockDelivery->agentPayments()->max('date');
        $scheduledAt = $stockDelivery->supplierPayments()->whereDate('date', '>', today())->min('date');
        if ($balanceAt) {
            $auto['balance_payment'] = $done($balanceAt, __('Payment recorded on the delivery'));
        } elseif ($scheduledAt) {
            $auto['balance_payment'] = ['status' => 'pending', 'date' => $date($scheduledAt), 'source' => __('Scheduled in the bank, not paid yet')];
        } elseif ($purchaseOrders->isNotEmpty() && $purchaseOrders->every(fn (PurchaseOrder $purchaseOrder) => $purchaseOrder->balance_paid_at)) {
            $auto['balance_payment'] = $done($purchaseOrders->pluck('balance_paid_at')->max(), __('Balance marked paid on the purchase orders'));
        }

        $costs = $stockDelivery->costs()->whereIn('type', [StockDeliveryCostTypeEnum::SHIPPING, StockDeliveryCostTypeEnum::DUTY])->get()->keyBy(fn (StockDeliveryCost $cost) => $cost->type->value);
        foreach (['shipping_invoice' => StockDeliveryCostTypeEnum::SHIPPING, 'duty_invoice' => StockDeliveryCostTypeEnum::DUTY] as $key => $type) {
            $cost = $costs->get($type->value);
            if ($cost?->is_na) {
                $auto[$key] = ['status' => 'na', 'date' => null, 'source' => __('N/A on the costing checklist')];
            } elseif ($cost && $cost->received_at && !$cost->is_estimate) {
                $auto[$key] = $done($cost->received_at, __('Invoice on the costing checklist'));
            }
        }

        $dutyBills = $stockDelivery->serviceInvoices()->whereIn('type', self::DUTY_BILL_TYPES)->get();
        if ($dutyBills->isNotEmpty() && $dutyBills->every(fn (StockDeliveryServiceInvoice $bill) => $bill->paid_at)) {
            $auto['duty_paid'] = $done($dutyBills->max('paid_at'), __('Customs bills marked paid'));
        }

        return $auto;
    }
}
