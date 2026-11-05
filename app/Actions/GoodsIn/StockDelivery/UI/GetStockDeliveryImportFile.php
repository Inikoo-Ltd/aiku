<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery\UI;

use App\Enums\GoodsIn\StockDelivery\StockDeliveryCostTypeEnum;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryServiceInvoiceTypeEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderAttachmentScopeEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryCost;
use App\Models\GoodsIn\StockDeliveryServiceInvoice;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderDepositPayment;
use App\Models\SupplyChain\Supplier;
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
 * Reading documents and booking their amounts stays with the costing checklist: this tab only shows the result.
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
        'bill_of_lading',
        'balance_payment',
        'original_documents',
        'shipping_invoice',
        'duty_invoice',
        'duty_paid',
        'duty_rates',
    ];

    public const array STATUSES = ['done', 'pending', 'na'];

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
            'commercial_invoice' => __('Commercial invoice / Packing list'),
            'bill_of_lading'     => __('Bill of lading'),
            'balance_payment'    => __('Balance payment'),
            'original_documents' => __('Original documents received / sent'),
            'shipping_invoice'   => __('Shipping invoice'),
            'duty_invoice'       => __('VAT / Duty invoice'),
            'duty_paid'          => __('Import VAT / Duty paid'),
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

        $steps = [];
        foreach (self::labels() as $key => $label) {
            $mark    = Arr::get($marks, $key);
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
                'files'    => match (true) {
                    $key === 'commercial_invoice' => $files->whereIn('scope', [PurchaseOrderAttachmentScopeEnum::INVOICE->value, PurchaseOrderAttachmentScopeEnum::PACKING_LIST->value])->values()->all(),
                    $this->scope($key) !== null   => $files->where('scope', $this->scope($key)->value)->values()->all(),
                    default                       => [],
                },
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
            'attach_route'  => [
                'method'     => 'post',
                'name'       => 'grp.models.stock-delivery.attachment.attach',
                'parameters' => ['stockDelivery' => $stockDelivery->id],
            ],
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

    private function scope(string $key): ?PurchaseOrderAttachmentScopeEnum
    {
        return match ($key) {
            'proforma'           => PurchaseOrderAttachmentScopeEnum::PROFORMA,
            'deposit', 'balance_payment', 'duty_paid' => PurchaseOrderAttachmentScopeEnum::BANK_STATEMENT,
            'shipping_quotes'    => PurchaseOrderAttachmentScopeEnum::SHIPPING_QUOTE,
            'commercial_invoice' => PurchaseOrderAttachmentScopeEnum::INVOICE,
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

        $hasInvoice     = $stockDelivery->agentInvoice()->exists() || $stockDelivery->supplierInvoice()->exists() || $files->where('scope', PurchaseOrderAttachmentScopeEnum::INVOICE->value)->isNotEmpty();
        $hasPackingList = $files->where('scope', PurchaseOrderAttachmentScopeEnum::PACKING_LIST->value)->isNotEmpty();
        if ($hasInvoice && $hasPackingList) {
            $auto['commercial_invoice'] = $done(Arr::get($stockDelivery->data, 'invoice_date'), __('Invoice and packing list on file'));
        }

        if ($files->where('scope', PurchaseOrderAttachmentScopeEnum::BILL_OF_LADING->value)->isNotEmpty()) {
            $auto['bill_of_lading'] = $done(null, __('Bill of lading attached'));
        }

        $balanceAt = $stockDelivery->supplierPayments()->max('date') ?? $stockDelivery->agentPayments()->max('date');
        if (!$balanceAt && $purchaseOrders->isNotEmpty() && $purchaseOrders->every(fn (PurchaseOrder $purchaseOrder) => $purchaseOrder->balance_paid_at)) {
            $balanceAt = $purchaseOrders->pluck('balance_paid_at')->max();
        }
        if ($balanceAt) {
            $auto['balance_payment'] = $done($balanceAt, __('Payment recorded on the delivery'));
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
