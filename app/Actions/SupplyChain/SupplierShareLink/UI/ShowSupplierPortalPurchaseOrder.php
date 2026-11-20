<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-06 14:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\SupplierShareLink\UI;

use App\Actions\Procurement\PurchaseOrder\PdfPurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\PdfPurchaseOrderLabels;
use App\Actions\Procurement\PurchaseOrder\UI\GetPurchaseOrderStages;
use App\Actions\SupplyChain\SupplierShareLink\NotifyPurchasingOfSupplierDepositRequest;
use App\Actions\SupplyChain\SupplierShareLink\StoreSupplierPortalPurchaseOrderDocument;
use App\Actions\SupplyChain\SupplierShareLink\UpdateSupplierPortalPurchaseOrderDeposit;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderAttachmentScopeEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStageEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Enums\SupplyChain\AgentPayment\AgentPaymentTypeEnum;
use App\Enums\SupplyChain\SupplierProduct\SupplierUnitEnum;
use App\Models\Helpers\Media;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderDepositPayment;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SupplyChain\Supplier;
use App\Actions\Procurement\PurchaseOrder\UI\GetPurchaseOrderNotes;
use App\Models\SupplyChain\SupplierShareLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * One purchase order sent to the supplier, as the supplier sees it: what the order document it received already says
 * (products, quantities, its own prices, terms), who submitted it and how far the order is up to hand over. Our costs,
 * extra costs, deliveries and containers stay out, and of the notes only the one written to the supplier and its own. The supplier sees the money paid to it for the order, by
 * the buyer (deposit payments) or by the agent from its ledger: a ledger entry typed deposit or balance is that, any
 * other payment is the deposit when it is the first and the balance after it, and sets its own production dates while the order is open.
 */
class ShowSupplierPortalPurchaseOrder
{
    use AsAction;

    private const array RELATIONS = [
        'organisation',
        'agent.organisation',
        'currency',
        'purchaseOrderTransactions.supplierProduct.currency',
        'purchaseOrderTransactions.supplierProduct.image',
        'purchaseOrderTransactions.supplierProduct.tradeUnits.image',
        'purchaseOrderTransactions.orgStock',
    ];

    /**
     * @return array{order: array<string, mixed>, journey: array<string, mixed>|null, payments: array<string, mixed>, deposit: array<string, mixed>, production: array<string, mixed>, documents: array<string, mixed>, notes: array{note_list: list<array<string, mixed>>}, lines: list<array<string, mixed>>}
     */
    public function handle(Supplier $supplier, int $purchaseOrderId, ?string $token = null): array
    {
        /** @var PurchaseOrder $purchaseOrder */
        $purchaseOrder = IndexSupplierPortalPurchaseOrders::make()->sent($supplier)->findOrFail($purchaseOrderId);
        $purchaseOrder->loadMissing(self::RELATIONS);

        $open  = in_array($purchaseOrder->state, PurchaseOrderStateEnum::open(), true);
        $lines = PdfPurchaseOrder::make()->lines($purchaseOrder);
        $data  = $purchaseOrder->data ?? [];

        return [
            'order'   => [
                'id'                    => $purchaseOrder->id,
                'reference'             => $purchaseOrder->reference,
                'organisation_name'     => $purchaseOrder->organisation->name,
                'agent_name'            => $purchaseOrder->agent?->organisation?->name,
                'state'                 => $purchaseOrder->state->value,
                'state_label'           => PurchaseOrderStateEnum::labels()[$purchaseOrder->state->value] ?? $purchaseOrder->state->value,
                'delivery_label'        => $open && $purchaseOrder->delivery_state ? (PurchaseOrderDeliveryStateEnum::labels()[$purchaseOrder->delivery_state->value] ?? null) : null,
                'open'                  => $open,
                'submitted_at'          => $purchaseOrder->submitted_at,
                'submitted_by'          => $this->submittedBy($purchaseOrder),
                'confirmed_at'          => $purchaseOrder->confirmed_at,
                'estimated_received_at' => $open ? $purchaseOrder->estimated_received_at : null,
                'settled_at'            => $purchaseOrder->state === PurchaseOrderStateEnum::SETTLED ? $purchaseOrder->settled_at : null,
                'currency_code'         => $purchaseOrder->currency->code,
                'total'                 => round($lines->sum(fn (PurchaseOrderTransaction $line) => (float) $line->net_amount), 2),
                'terms'                 => array_values(array_filter([
                    ['label' => __('Incoterm'), 'value' => Arr::get($data, 'incoterm')],
                    ['label' => __('Port of export'), 'value' => Arr::get($data, 'port_of_export')],
                    ['label' => __('Port of import'), 'value' => Arr::get($data, 'port_of_import')],
                    ['label' => __('Payment'), 'value' => Arr::get($data, 'payment_terms')],
                ], fn (array $term) => filled($term['value']))),
                'labels'                => $open
                    ? collect(PdfPurchaseOrderLabels::LEVELS)->mapWithKeys(fn (string $level) => [$level => PdfPurchaseOrderLabels::make()->url($purchaseOrder, [$level])])->all()
                    : null,
            ],
            'journey'    => IndexSupplierPortalPurchaseOrderStages::make()->forPurchaseOrder($supplier, $purchaseOrder->id),
            'payments'   => $this->payments($purchaseOrder, $token),
            'deposit'    => $this->deposit($purchaseOrder, $supplier),
            'production' => $this->production($purchaseOrder),
            'documents'  => $this->documents($purchaseOrder, $token),
            'notes'      => GetPurchaseOrderNotes::run($purchaseOrder, GetPurchaseOrderNotes::VIEWER_SUPPLIER, true),
            'lines'      => $lines->map(fn (PurchaseOrderTransaction $line) => $this->line($line, $purchaseOrder))->values()->all(),
        ];
    }

    /**
     * The person who sent the order: the user on the audit that moved it to submitted, else its buyer.
     */
    private function submittedBy(PurchaseOrder $purchaseOrder): ?string
    {
        $submitter = DB::table('audits')
            ->join('users', 'users.id', 'audits.user_id')
            ->where('audits.auditable_type', 'PurchaseOrder')
            ->where('audits.auditable_id', $purchaseOrder->id)
            ->where('audits.user_type', 'User')
            ->whereRaw("audits.new_values->>'state' = ?", [PurchaseOrderStateEnum::SUBMITTED->value])
            ->orderByDesc('audits.id')
            ->first(['users.contact_name', 'users.username'])
            ?? $purchaseOrder->buyer()->first(['contact_name', 'username']);

        return $submitter ? ($submitter->contact_name ?: $submitter->username) : null;
    }

    /**
     * @return array{currency_code: string, total: float, paid: float, left: float, deposit_paid_at: mixed, balance_paid_at: mixed, deposit: list<array<string, mixed>>, balance: list<array<string, mixed>>}
     */
    private function payments(PurchaseOrder $purchaseOrder, ?string $token): array
    {
        $payments = $this->buyerPayments($purchaseOrder, $token)
            ->concat($purchaseOrder->isAgentOrder() ? $this->agentPayments($purchaseOrder, $token) : [])
            ->sortBy([['date', 'asc'], ['sort', 'asc']])
            ->values();

        $total = round((float) $purchaseOrder->cost_items, 2);
        $paid  = round((float) $payments->sum('amount'), 2);

        $isDeposit = fn (array $payment, int $index) => match ($payment['type']) {
            AgentPaymentTypeEnum::SUPPLIER_DEPOSIT->value => true,
            AgentPaymentTypeEnum::SUPPLIER_BALANCE->value => false,
            default                                       => $index === 0,
        };
        [$deposit, $balance] = $payments->partition($isDeposit);

        $present = fn (Collection $rows) => $rows->map(fn (array $payment) => Arr::except($payment, ['type', 'sort']))->values()->all();

        return [
            'currency_code'   => $purchaseOrder->currency->code,
            'total'           => $total,
            'paid'            => $paid,
            'left'            => max(0, round($total - $paid, 2)),
            'deposit_paid_at' => $purchaseOrder->deposit_paid_at,
            'balance_paid_at' => $purchaseOrder->balance_paid_at,
            'deposit'         => $present($deposit),
            'balance'         => $present($balance),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function buyerPayments(PurchaseOrder $purchaseOrder, ?string $token): Collection
    {
        return $purchaseOrder->depositPayments()
            ->with(['originalCurrency:id,code', 'bankStatement:id,ulid,file_name'])
            ->get()
            ->map(fn (PurchaseOrderDepositPayment $payment) => [
                'type'                   => null,
                'sort'                   => $payment->id,
                'date'                   => $payment->date->toDateString(),
                'amount'                 => round((float) $payment->amount, 2),
                'original_amount'        => $payment->original_amount !== null ? round((float) $payment->original_amount, 2) : null,
                'original_currency_code' => $payment->originalCurrency?->code,
                'reference'              => $payment->reference,
                'paid_by'                => $purchaseOrder->organisation->name,
                'receipt'                => $payment->bankStatement ? $this->receipt($purchaseOrder, $payment->bankStatement->ulid, $payment->bankStatement->file_name, $token) : null,
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function agentPayments(PurchaseOrder $purchaseOrder, ?string $token): Collection
    {
        return DB::table('agent_payments')
            ->leftJoin('currencies as original_currencies', 'original_currencies.id', 'agent_payments.original_currency_id')
            ->leftJoin('media as receipts', 'receipts.id', 'agent_payments.media_id')
            ->where('agent_payments.purchase_order_id', $purchaseOrder->id)
            ->whereIn('agent_payments.type', AgentPaymentTypeEnum::supplierPaymentValues())
            ->whereNull('agent_payments.deleted_at')
            ->whereNotNull('agent_payments.date')
            ->get(['agent_payments.id', 'agent_payments.type', 'agent_payments.date', 'agent_payments.amount', 'agent_payments.original_amount', 'original_currencies.code as original_currency_code', 'agent_payments.reference', 'receipts.ulid as receipt_ulid', 'receipts.file_name as receipt_file_name'])
            ->map(fn (object $payment) => [
                'type'                   => $payment->type,
                'sort'                   => $payment->id,
                'date'                   => substr((string) $payment->date, 0, 10),
                'amount'                 => round((float) $payment->amount, 2),
                'original_amount'        => $payment->original_amount !== null ? round((float) $payment->original_amount, 2) : null,
                'original_currency_code' => $payment->original_currency_code,
                'reference'              => $payment->reference,
                'paid_by'                => $purchaseOrder->agent?->organisation?->name,
                'receipt'                => $payment->receipt_ulid ? $this->receipt($purchaseOrder, $payment->receipt_ulid, $payment->receipt_file_name, $token) : null,
            ]);
    }

    /**
     * The transfer receipt of a payment. The supplier opens it through its own link; staff previewing the page through the staff media route.
     *
     * @return array{file_name: string|null, view_url: string, download_url: string}
     */
    private function receipt(PurchaseOrder $purchaseOrder, string $ulid, ?string $fileName, ?string $token): array
    {
        $parameters = $token === null
            ? ['media' => $ulid]
            : ['token' => $token, 'purchaseOrder' => $purchaseOrder->id, 'media' => $ulid];
        $route      = $token === null ? 'grp.media.download' : 'grp.supplier_share.purchase_order.receipt';

        return [
            'file_name'    => $fileName,
            'view_url'     => route($route, [...$parameters, 'inline' => true]),
            'download_url' => route($route, $parameters),
        ];
    }

    public function isPaymentReceipt(PurchaseOrder $purchaseOrder, Media $media): bool
    {
        return $purchaseOrder->depositPayments()->where('media_id', $media->id)->exists()
            || ($purchaseOrder->isAgentOrder() && DB::table('agent_payments')
                ->where('purchase_order_id', $purchaseOrder->id)
                ->where('media_id', $media->id)
                ->whereIn('type', AgentPaymentTypeEnum::supplierPaymentValues())
                ->whereNull('deleted_at')
                ->whereNotNull('date')
                ->exists());
    }

    public function supplierDocumentsQuery(PurchaseOrder $purchaseOrder): MorphToMany
    {
        return $purchaseOrder->attachments()->wherePivot('sub_scope', StoreSupplierPortalPurchaseOrderDocument::SUB_SCOPE);
    }

    /**
     * The shipping and customs documents the supplier uploaded to the order, and the types it can upload.
     *
     * @return array{open: bool, types: list<array{value: string, label: string}>, files: list<array<string, mixed>>}
     */
    private function documents(PurchaseOrder $purchaseOrder, ?string $token): array
    {
        $labels = PurchaseOrderAttachmentScopeEnum::labels();

        return [
            'open'  => in_array($purchaseOrder->state, PurchaseOrderStateEnum::open(), true),
            'types' => array_map(fn (PurchaseOrderAttachmentScopeEnum $scope) => ['value' => $scope->value, 'label' => $labels[$scope->value]], PurchaseOrderAttachmentScopeEnum::supplierPortal()),
            'files' => $this->supplierDocumentsQuery($purchaseOrder)
                ->orderBy('model_has_attachments.id')
                ->get(['media.id', 'media.ulid'])
                ->map(function (Media $media) use ($purchaseOrder, $token, $labels) {
                    $parameters = $token === null
                        ? ['media' => $media->ulid]
                        : ['token' => $token, 'purchaseOrder' => $purchaseOrder->id, 'media' => $media->ulid];
                    $route      = $token === null ? 'grp.media.download' : 'grp.supplier_share.purchase_order.document';

                    return [
                        'type'         => $labels[$media->pivot->scope] ?? $media->pivot->scope,
                        'file_name'    => $media->pivot->caption,
                        'uploaded_at'  => $media->pivot->created_at,
                        'view_url'     => route($route, [...$parameters, 'inline' => true]),
                        'download_url' => route($route, $parameters),
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    /**
     * What the supplier answered at the deposit stage. It chooses once, while the order is open, nothing is paid, no
     * deposit amount is set and no later stage is reached: no deposit needed, or a deposit of a fixed share.
     *
     * @return array{choosable: bool, not_needed: bool, request: array{percentage: int, amount: float, requested_at: string}|null, request_percentage: int, request_amount: float, recipients: list<array{name: string, organisation: string}>}
     */
    public function deposit(PurchaseOrder $purchaseOrder, Supplier $supplier): array
    {
        $request   = Arr::get($purchaseOrder->data ?? [], 'supplier_deposit_request');
        $notNeeded = $purchaseOrder->deposit_amount !== null && (float) $purchaseOrder->deposit_amount == 0 && !$purchaseOrder->isDepositPaid();

        $choosable = $request === null
            && $purchaseOrder->deposit_amount === null
            && in_array($purchaseOrder->state, PurchaseOrderStateEnum::open(), true)
            && !$purchaseOrder->isDepositPaid()
            && !(GetPurchaseOrderStages::run($purchaseOrder)[PurchaseOrderStageEnum::DEPOSIT_PAID->value]['reached'] ?? false);

        return [
            'choosable'          => $choosable,
            'not_needed'         => $notNeeded,
            'request'            => $request ? Arr::only($request, ['percentage', 'amount', 'requested_at']) : null,
            'request_percentage' => UpdateSupplierPortalPurchaseOrderDeposit::REQUEST_PERCENTAGE,
            'request_amount'     => UpdateSupplierPortalPurchaseOrderDeposit::requestAmount($purchaseOrder),
            'recipients'         => $choosable ? NotifyPurchasingOfSupplierDepositRequest::make()->emailRecipients($purchaseOrder) : [],
            'agreement_missing'  => $choosable && UpdateSupplierPortalPurchaseOrderDeposit::isAgreementMissing($supplier),
        ];
    }

    /**
     * The supplier's own production dates. It records them once the terms are agreed, until the goods are handed over.
     *
     * @return array{started_at: mixed, expected_finish_at: string|null, editable: bool, blocked: string|null}
     */
    public function production(PurchaseOrder $purchaseOrder): array
    {
        $open    = in_array($purchaseOrder->state, PurchaseOrderStateEnum::open(), true);
        $blocked = null;
        if ($open) {
            $stages  = GetPurchaseOrderStages::run($purchaseOrder);
            $blocked = match (true) {
                $purchaseOrder->state === PurchaseOrderStateEnum::SUBMITTED => __('Production can be set once the order is agreed.'),
                $stages[PurchaseOrderStageEnum::HANDED_OVER->value]['reached'] ?? false => __('The goods are handed over.'),
                default => null,
            };
        }

        return [
            'started_at'         => $purchaseOrder->production_started_at,
            'expected_finish_at' => Arr::get($purchaseOrder->data ?? [], 'estimated_production_date'),
            'editable'           => $open && $blocked === null,
            'blocked'            => $blocked,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function line(PurchaseOrderTransaction $line, PurchaseOrder $purchaseOrder): array
    {
        $product         = $line->supplierProduct;
        $imageOwner      = $product?->image_id ? $product : $product?->tradeUnits->first(fn ($tradeUnit) => $tradeUnit->image_id !== null);
        $unitsPerCarton  = (float) ($product?->units_per_carton ?: 0);
        $perSupplierUnit = $product?->supplier_unit ? $product->unitsPerSupplierUnit() : 1.0;
        $unitCost        = (float) ($line->unit_cost ?? $product?->cost);
        $ourCode         = $product && $line->orgStock && $line->orgStock->code !== $product->code ? $line->orgStock->code : null;

        return [
            'id'               => $line->id,
            'code'             => $product?->code ?? $line->orgStock?->code,
            'our_code'         => $ourCode,
            'name'             => $product?->name ?? $line->orgStock?->name,
            'image'            => $imageOwner?->imageSources(80, 80),
            'units_per_carton' => $unitsPerCarton ?: null,
            'cartons'          => $unitsPerCarton ? round((float) $line->quantity_ordered / $unitsPerCarton, 3) : null,
            'quantity'         => round((float) $line->quantity_ordered / $perSupplierUnit, 3),
            'unit'             => $product?->supplier_unit ? SupplierUnitEnum::labels()[$product->supplier_unit->value] : null,
            'unit_price'       => $unitCost * $perSupplierUnit,
            'amount'           => (float) $line->net_amount,
            'currency_code'    => $product?->currency?->code ?? $purchaseOrder->currency->code,
            'cancelled'        => $line->state === PurchaseOrderTransactionStateEnum::CANCELLED,
        ];
    }

    public function asController(string $token, int $purchaseOrder): JsonResponse
    {
        $link = SupplierShareLink::findLiveByToken($token);
        abort_if($link->needsCode(), 403);

        return response()->json($this->handle($link->supplier, $purchaseOrder, $token));
    }
}
