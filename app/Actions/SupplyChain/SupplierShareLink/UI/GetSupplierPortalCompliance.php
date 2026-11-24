<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-24 11:34
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\SupplierShareLink\UI;

use App\Actions\Procurement\PurchaseOrder\UI\GetPurchaseOrderCompliance;
use App\Enums\Goods\TradeUnit\TradeAttachmentScopeEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\SupplyChain\SupplierSubmission\SupplierSubmissionStateEnum;
use App\Enums\SupplyChain\SupplierSubmission\SupplierSubmissionTypeEnum;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Media;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SupplyChain\SupplierSubmission;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The compliance documents of a supplier's products, as the supplier sees them in its portal. Every document is
 * optional for now, so nothing is "missing": each one is on file, not sent, waiting for our review, expired or about
 * to expire, or sent back for more information or not accepted. The supplier sees only that status and the files it
 * sent itself, never the files themselves when they came from someone else: a trade unit is often bought from
 * several suppliers.
 */
class GetSupplierPortalCompliance
{
    use AsObject;

    public const int EXPIRY_WARNING_DAYS = 30;

    public const array ATTENTION_STATUSES = ['expired', 'expiring', 'more_information', 'not_accepted'];

    /**
     * The products on the supplier's open purchase orders and in its plan for next orders.
     *
     * @return array{products: list<array<string, mixed>>, document_types: list<array{value: string, label: string}>}
     */
    public function handle(Supplier $supplier): array
    {
        $openOrders = $this->openOrdersByProduct($supplier);
        $planned    = collect(GetSupplierPortalOrderPlan::make()->handle($supplier, withCompliance: false)['products'])
            ->filter(fn (array $product) => ($product['status'] ?? null) !== 'no_order')
            ->pluck('id');

        $productIds = $openOrders->keys()->merge($planned)->unique()->values();

        return [
            'products'       => $this->products($supplier, $productIds, $openOrders, $planned),
            'document_types' => $this->documentTypes(),
        ];
    }

    /**
     * The same rows for the products of one purchase order, to show inside it.
     *
     * @param  Collection<int, int>  $supplierProductIds
     * @return list<array<string, mixed>>
     */
    public function forProducts(Supplier $supplier, Collection $supplierProductIds): array
    {
        return $this->products($supplier, $supplierProductIds->filter()->unique()->values(), collect(), collect());
    }

    /**
     * The supplier products, among the ones given, with a document that needs the supplier to act.
     *
     * @param  Collection<int, int>  $supplierProductIds
     * @return Collection<int, int>
     */
    public function needingAttention(Supplier $supplier, Collection $supplierProductIds): Collection
    {
        return collect($this->forProducts($supplier, $supplierProductIds))
            ->filter(fn (array $product) => $product['needs_attention'])
            ->pluck('id');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function documentTypes(): array
    {
        $labels = TradeAttachmentScopeEnum::labels();

        return array_map(fn (TradeAttachmentScopeEnum $scope) => ['value' => $scope->value, 'label' => $labels[$scope->value]], GetPurchaseOrderCompliance::DOCUMENTS);
    }

    /**
     * @param  Collection<int, int>  $productIds
     * @param  Collection<int, Collection<int, array{id: int, reference: string}>>  $openOrders
     * @param  Collection<int, int>  $planned
     * @return list<array<string, mixed>>
     */
    private function products(Supplier $supplier, Collection $productIds, Collection $openOrders, Collection $planned): array
    {
        if ($productIds->isEmpty()) {
            return [];
        }

        $submissions = SupplierSubmission::where('supplier_id', $supplier->id)
            ->where('type', SupplierSubmissionTypeEnum::COMPLIANCE_DOCUMENT)
            ->whereIn('supplier_product_id', $productIds)
            ->where('state', '!=', SupplierSubmissionStateEnum::DRAFT)
            ->with('attachments')
            ->orderByDesc('id')
            ->get()
            ->groupBy(fn (SupplierSubmission $submission) => $submission->supplier_product_id.'|'.Arr::get($submission->data, 'document_type'));

        return SupplierProduct::where('supplier_id', $supplier->id)
            ->whereIn('id', $productIds)
            ->with(['tradeUnits.attachments', 'tradeUnits.documentsTradeUnit.attachments'])
            ->orderBy('code')
            ->get()
            ->map(function (SupplierProduct $product) use ($submissions, $openOrders, $planned) {
                /** @var TradeUnit|null $tradeUnit */
                $tradeUnit = $product->tradeUnits->first();
                $onFile    = $tradeUnit ? collect(GetPurchaseOrderCompliance::make()->documents($tradeUnit)['documents'])->keyBy('scope') : collect();

                $documents = collect($this->documentTypes())->map(fn (array $type) => $this->document(
                    $type,
                    Arr::get($onFile->get($type['value']) ?? [], 'files', []),
                    $submissions->get($product->id.'|'.$type['value']) ?? collect()
                ))->values();

                return [
                    'id'              => $product->id,
                    'code'            => $product->code,
                    'name'            => $product->name,
                    'has_trade_unit'  => $tradeUnit !== null,
                    'open_orders'     => ($openOrders->get($product->id) ?? collect())->values()->all(),
                    'in_plan'         => $planned->contains($product->id),
                    'documents'       => $documents->all(),
                    'needs_attention' => $documents->contains(fn (array $document) => in_array($document['status'], self::ATTENTION_STATUSES, true)),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array{value: string, label: string}  $type
     * @param  array<int, array<string, mixed>>  $filesOnFile
     * @param  Collection<int, SupplierSubmission>  $submissions
     * @return array<string, mixed>
     */
    private function document(array $type, array $filesOnFile, Collection $submissions): array
    {
        $latest     = $submissions->first();
        $expiresOn  = collect($filesOnFile)->pluck('expires_on')->filter()->sort()->last();
        $hasUndated = collect($filesOnFile)->contains(fn (array $file) => empty($file['expires_on']));

        $status = match (true) {
            $latest && $latest->state->isOpen()                                           => 'waiting',
            $latest?->state === SupplierSubmissionStateEnum::MORE_INFORMATION_REQUESTED   => 'more_information',
            $filesOnFile !== [] && !$hasUndated && $expiresOn && Carbon::parse($expiresOn)->isPast() => 'expired',
            $filesOnFile !== [] && !$hasUndated && $expiresOn && Carbon::parse($expiresOn)->lte(now()->addDays(self::EXPIRY_WARNING_DAYS)) => 'expiring',
            $filesOnFile !== []                                                           => 'on_file',
            $latest?->state === SupplierSubmissionStateEnum::NOT_PROCEEDING               => 'not_accepted',
            default                                                                       => 'not_sent',
        };

        return [
            'scope'         => $type['value'],
            'label'         => $type['label'],
            'status'        => $status,
            'expires_on'    => $expiresOn,
            'feedback'      => $latest?->feedback,
            'submission_id' => $latest?->id,
            'my_files'      => $submissions->flatMap(fn (SupplierSubmission $submission) => $submission->attachments->map(fn (Media $media) => [
                'submission_id' => $submission->id,
                'name'          => $media->pivot->caption ?: $media->file_name,
                'ulid'          => $media->ulid,
                'state'         => $submission->state->value,
                'sent_at'       => $submission->submitted_at,
            ]))->values()->all(),
        ];
    }

    /**
     * The references of the supplier's open orders each of its products is on.
     *
     * @return Collection<int, Collection<int, array{id: int, reference: string}>>
     */
    private function openOrdersByProduct(Supplier $supplier): Collection
    {
        $orderIds = IndexSupplierPortalPurchaseOrders::make()->sent($supplier)
            ->whereIn('purchase_orders.state', PurchaseOrderStateEnum::open())
            ->select('purchase_orders.id');

        return DB::table('purchase_order_transactions as pot')
            ->join('purchase_orders as po', 'po.id', 'pot.purchase_order_id')
            ->whereIn('pot.purchase_order_id', $orderIds)
            ->whereNotNull('pot.supplier_product_id')
            ->where('pot.state', '!=', 'cancelled')
            ->distinct()
            ->get(['pot.supplier_product_id', 'po.id', 'po.reference'])
            ->groupBy('supplier_product_id')
            ->map(fn (Collection $rows) => $rows->map(fn (object $row) => ['id' => (int) $row->id, 'reference' => $row->reference])->unique('id')->values());
    }
}
