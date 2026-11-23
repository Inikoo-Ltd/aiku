<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\EInvoice;

use App\Actions\Accounting\Traits\AuthorizesAccountingEdit;
use App\Actions\OrgAction;
use App\Enums\Accounting\ReceivedEInvoice\ReceivedEInvoiceMatchStateEnum;
use App\Models\Accounting\ReceivedEInvoice;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * Staff say who sent a received e-invoice (and, optionally, which order and delivery it is for), set it aside as not
 * needing a match, or send it back to be matched. Only the link is recorded: no cost or payable is written.
 */
class MatchReceivedEInvoice extends OrgAction
{
    use AuthorizesAccountingEdit;

    private ?User $user = null;

    public function handle(ReceivedEInvoice $receivedEInvoice, array $modelData): ReceivedEInvoice
    {
        return DB::transaction(function () use ($receivedEInvoice, $modelData) {
            $receivedEInvoice = ReceivedEInvoice::lockForUpdate()->findOrFail($receivedEInvoice->id);
            $state            = ReceivedEInvoiceMatchStateEnum::from($modelData['match_state']);
            $organisationId   = $receivedEInvoice->organisation_id;

            $supplierId = $purchaseOrderId = $stockDeliveryId = null;
            if ($state === ReceivedEInvoiceMatchStateEnum::MATCHED) {
                $supplierId = $modelData['supplier_id'] ?? null;
                if (!$supplierId || !DB::table('org_suppliers')->where('organisation_id', $organisationId)->where('supplier_id', $supplierId)->exists()) {
                    throw ValidationException::withMessages(['supplier_id' => __('Choose a supplier of this organisation')]);
                }
                $purchaseOrderId = $this->documentId(PurchaseOrder::class, $organisationId, $supplierId, $modelData['purchase_order_reference'] ?? null, 'purchase_order_reference');
                $stockDeliveryId = $this->documentId(StockDelivery::class, $organisationId, $supplierId, $modelData['stock_delivery_reference'] ?? null, 'stock_delivery_reference');
            }

            $receivedEInvoice->update([
                'match_state'       => $state,
                'supplier_id'       => $supplierId,
                'purchase_order_id' => $purchaseOrderId,
                'stock_delivery_id' => $stockDeliveryId,
                'matched_at'        => $state === ReceivedEInvoiceMatchStateEnum::UNMATCHED ? null : now(),
                'match_data'        => array_merge($receivedEInvoice->match_data, [
                    'decided_by'   => $this->user?->contact_name ?: $this->user?->username,
                    'decided_at'   => now()->toIso8601String(),
                    'decided_note' => $modelData['note'] ?? null,
                ]),
            ]);

            return $receivedEInvoice;
        });
    }

    /**
     * @param class-string<PurchaseOrder|StockDelivery> $model
     */
    private function documentId(string $model, int $organisationId, int $supplierId, ?string $reference, string $field): ?int
    {
        $reference = trim((string)$reference);
        if ($reference === '') {
            return null;
        }

        $ids = $model::where('organisation_id', $organisationId)
            ->where('reference', $reference)
            ->where('supplier_id', $supplierId)
            ->limit(2)
            ->pluck('id');
        if ($ids->count() !== 1) {
            throw ValidationException::withMessages([$field => __(':reference is not a single order or delivery of this supplier in this organisation', ['reference' => $reference])]);
        }

        return $ids->first();
    }

    public function rules(): array
    {
        return [
            'match_state'              => ['required', Rule::enum(ReceivedEInvoiceMatchStateEnum::class)],
            'supplier_id'              => ['sometimes', 'nullable', 'integer'],
            'purchase_order_reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'stock_delivery_reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'note'                     => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    public function action(ReceivedEInvoice $receivedEInvoice, array $modelData): ReceivedEInvoice
    {
        $this->asAction = true;
        $this->initialisation($receivedEInvoice->organisation, $modelData);

        return $this->handle($receivedEInvoice, $this->validatedData);
    }

    public function asController(Organisation $organisation, ReceivedEInvoice $receivedEInvoice, ActionRequest $request): ReceivedEInvoice
    {
        $this->user = $request->user();
        $this->initialisation($organisation, $request);
        abort_unless($receivedEInvoice->organisation_id === $organisation->id, 404);

        return $this->handle($receivedEInvoice, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
