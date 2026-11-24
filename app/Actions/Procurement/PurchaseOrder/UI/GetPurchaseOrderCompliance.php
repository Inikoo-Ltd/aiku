<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Oct 2026 Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder\UI;

use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Goods\TradeUnit\TradeAttachmentScopeEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Media;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The compliance documents each product of a purchase order has, kept on its trade unit so they count for every
 * order. Transit takes weeks, so they are due by the arrival estimate less a buffer, not before shipping; only the
 * documents the supplier alone can provide are needed before QC is approved.
 */
class GetPurchaseOrderCompliance
{
    use AsObject;

    public const int DEADLINE_BUFFER_DAYS = 10;

    public static function bufferDays(PurchaseOrder $purchaseOrder): int
    {
        return (int) Arr::get($purchaseOrder->organisation->settings, 'procurement.compliance_buffer_days', self::DEADLINE_BUFFER_DAYS);
    }

    public const array DOCUMENTS = [
        TradeAttachmentScopeEnum::SDS,
        TradeAttachmentScopeEnum::IFRA,
        TradeAttachmentScopeEnum::CLP,
        TradeAttachmentScopeEnum::ALLERGEN_DECLARATIONS,
        TradeAttachmentScopeEnum::DOC,
        TradeAttachmentScopeEnum::CPSR,
        TradeAttachmentScopeEnum::TEST_REPORTS,
    ];

    /**
     * The documents a product must have. Every document is optional until the compliance rules are set.
     *
     * @return TradeAttachmentScopeEnum[]
     */
    public function requiredDocuments(TradeUnit $tradeUnit): array
    {
        return [];
    }

    /**
     * Of the required documents, those only the supplier can provide, needed before QC is approved.
     *
     * @return TradeAttachmentScopeEnum[]
     */
    public function supplierOnlyDocuments(TradeUnit $tradeUnit): array
    {
        return [];
    }

    public function handle(PurchaseOrder $purchaseOrder): array
    {
        $eta      = $this->eta($purchaseOrder);
        $deadline = $eta?->copy()->subDays(self::bufferDays($purchaseOrder));

        $products = $this->tradeUnits($purchaseOrder)->map(fn (TradeUnit $tradeUnit) => [
            'trade_unit_id' => $tradeUnit->id,
            'code'          => $tradeUnit->code,
            'name'          => $tradeUnit->name,
            ...$this->documents($tradeUnit),
        ])->values();

        $missing = $products->sum(fn (array $product) => count($product['missing']));

        return [
            'eta'                => $eta?->toDateString(),
            'deadline'           => $deadline?->toDateString(),
            'days_to_deadline'   => $deadline ? (int) now()->startOfDay()->diffInDays($deadline, false) : null,
            'missing'            => $missing,
            'overdue'            => $missing > 0 && $deadline && $deadline->lt(now()->startOfDay()),
            'products'           => $products->all(),
        ];
    }

    /**
     * @return array{documents: array<int, array{scope: string, label: string, count: int, files: array<int, array{name: string, purchase_order_reference: ?string, download_route: array{name: string, parameters: array<string, string>}}>, required: bool, supplier_only: bool}>, missing: string[], missing_supplier_only: string[]}
     */
    public function documents(TradeUnit $tradeUnit): array
    {
        $filesByScope = $tradeUnit->attachmentsWithSharedDocuments()
            ->groupBy(fn (Media $attachment) => str_replace(['_private', 'msds'], ['', 'sds'], (string) $attachment->pivot->scope))
            ->map(fn (Collection $attachments) => $attachments->map(fn (Media $attachment) => [
                'name'                     => $attachment->file_name,
                'purchase_order_reference' => $this->uploadedFromPurchaseOrderReference($attachment),
                'download_route'           => [
                    'name'       => 'grp.media.download',
                    'parameters' => ['media' => $attachment->ulid],
                ],
            ])->values()->all());
        $required     = array_map(fn (TradeAttachmentScopeEnum $scope) => $scope->value, $this->requiredDocuments($tradeUnit));
        $supplierOnly = array_map(fn (TradeAttachmentScopeEnum $scope) => $scope->value, $this->supplierOnlyDocuments($tradeUnit));

        $documents = collect(self::DOCUMENTS)->map(fn (TradeAttachmentScopeEnum $scope) => [
            'scope'         => $scope->value,
            'label'         => TradeAttachmentScopeEnum::labels()[$scope->value],
            'count'         => count($filesByScope->get($scope->value, [])),
            'files'         => $filesByScope->get($scope->value, []),
            'required'      => in_array($scope->value, $required),
            'supplier_only' => in_array($scope->value, $supplierOnly),
        ]);

        return [
            'documents'             => $documents->all(),
            'missing'               => $documents->filter(fn (array $document) => $document['required'] && !$document['count'])->pluck('label')->values()->all(),
            'missing_supplier_only' => $documents->filter(fn (array $document) => $document['supplier_only'] && !$document['count'])->pluck('label')->values()->all(),
        ];
    }

    /**
     * Every product of the order with its compliance documents, each checked once it has a file or someone ticked it
     * as checked and OK for this order.
     *
     * @return array<int, array{trade_unit_id: int, code: string, name: ?string, route: array{name: string, parameters: array<int, string>}, documents: array<int, array{scope: string, label: string, files: array<int, array{name: string, purchase_order_reference: ?string, download_route: array{name: string, parameters: array<string, string>}}>, has_files: bool, checked: bool, checked_by: ?string, checked_at: ?string}>}>
     */
    public function checklist(PurchaseOrder $purchaseOrder): array
    {
        $checks    = Arr::get($purchaseOrder->data, 'compliance_checks', []);
        $userNames = User::whereIn('id', collect($checks)->flatten(1)->pluck('user_id')->filter()->unique())
            ->get()
            ->mapWithKeys(fn (User $user) => [$user->id => $user->chatName()]);

        return $this->tradeUnits($purchaseOrder)->map(fn (TradeUnit $tradeUnit) => [
            'trade_unit_id' => $tradeUnit->id,
            'code'          => $tradeUnit->code,
            'name'          => $tradeUnit->name,
            'route'         => [
                'name'       => 'grp.trade_units.units.show',
                'parameters' => [$tradeUnit->slug],
            ],
            'documents'     => collect($this->documents($tradeUnit)['documents'])->map(function (array $document) use ($checks, $tradeUnit, $userNames) {
                $check = Arr::get($checks, $tradeUnit->id.'.'.$document['scope']);

                return [
                    'scope'      => $document['scope'],
                    'label'      => $document['label'],
                    'files'      => $document['files'],
                    'has_files'  => $document['count'] > 0,
                    'checked'    => $document['count'] > 0 || $check !== null,
                    'checked_by' => $check ? $userNames->get(Arr::get($check, 'user_id')) : null,
                    'checked_at' => $check ? Arr::get($check, 'at') : null,
                ];
            })->values()->all(),
        ])->sortBy('code')->values()->all();
    }

    private function uploadedFromPurchaseOrderReference(Media $attachment): ?string
    {
        $data = $attachment->pivot->data ?? null;
        if (is_string($data)) {
            $data = json_decode($data, true);
        }

        return Arr::get($data ?? [], 'purchase_order_reference');
    }

    /**
     * "SDS for ABC-01" for each document only the supplier can provide that is still missing.
     *
     * @return string[]
     */
    public function missingSupplierOnly(PurchaseOrder $purchaseOrder): array
    {
        return $this->tradeUnits($purchaseOrder)
            ->flatMap(fn (TradeUnit $tradeUnit) => array_map(
                fn (string $document) => __(':document for :code', ['document' => $document, 'code' => $tradeUnit->code]),
                $this->documents($tradeUnit)['missing_supplier_only']
            ))
            ->values()
            ->all();
    }

    /**
     * "SDS for ABC-01" for each required document still missing.
     *
     * @return string[]
     */
    public function missingRequired(PurchaseOrder $purchaseOrder): array
    {
        return $this->tradeUnits($purchaseOrder)
            ->flatMap(fn (TradeUnit $tradeUnit) => array_map(
                fn (string $document) => __(':document for :code', ['document' => $document, 'code' => $tradeUnit->code]),
                $this->documents($tradeUnit)['missing']
            ))
            ->values()
            ->all();
    }

    public function eta(PurchaseOrder $purchaseOrder): ?Carbon
    {
        $deliveryEstimate = $purchaseOrder->stockDeliveries()
            ->whereNotIn('stock_deliveries.state', [StockDeliveryStateEnum::CANCELLED, StockDeliveryStateEnum::NOT_RECEIVED])
            ->get()
            ->map(fn ($stockDelivery) => Arr::get($stockDelivery->data, 'estimated_receiving_date'))
            ->filter()
            ->max();

        $estimate = $deliveryEstimate ?: $purchaseOrder->estimatedReceivingDate();

        return $estimate ? Carbon::parse($estimate)->startOfDay() : null;
    }

    /**
     * @return Collection<int, TradeUnit>
     */
    public function tradeUnits(PurchaseOrder $purchaseOrder): Collection
    {
        return $purchaseOrder->purchaseOrderTransactions()
            ->whereNotIn('state', [PurchaseOrderTransactionStateEnum::CANCELLED])
            ->with('orgStock.tradeUnits.attachments', 'orgStock.tradeUnits.documentsTradeUnit.attachments')
            ->get()
            ->flatMap(fn ($transaction) => $transaction->orgStock?->tradeUnits ?? [])
            ->unique('id')
            ->values();
    }
}
