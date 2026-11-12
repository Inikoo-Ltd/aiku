<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 14 Oct 2026 04:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\SupplierAgreedTerm\UI;

use App\Actions\Procurement\SupplierAgreedTerm\GetPurchaseOrderAgreedTerms;
use App\Actions\SupplyChain\SupplierAgreement\GetSupplierAgreementStatus;
use App\Enums\Procurement\ProcurementNote\ProcurementNoteCategoryEnum;
use App\Enums\Procurement\ProcurementNote\ProcurementNoteIncidentOutcomeEnum;
use App\Enums\Procurement\ProcurementNote\ProcurementNoteSeverityEnum;
use App\Enums\Procurement\ProcurementNote\ProcurementNoteVisibilityEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\SupplyChain\SupplierAgreement\SupplierAgreementStatusEnum;
use App\Http\Resources\Procurement\ProcurementNoteResource;
use App\Http\Resources\Procurement\SupplierAgreedTermResource;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\ProcurementNote;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SupplyChain\SupplierAgreementAcceptance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetPurchaseOrderAgreement
{
    use AsObject;

    /**
     * What the buyer must have in front of them on a purchase order: the terms in force for its
     * supplier (or agent) and the products on its lines, and the pinned or critical notes on the
     * order, the supplier, its agent and those products. A critical note has to be acknowledged
     * before the order is submitted.
     *
     * @return array<string, mixed>
     */
    public function handle(PurchaseOrder $purchaseOrder, bool $forAgent = false): array
    {
        $terms = GetPurchaseOrderAgreedTerms::run($purchaseOrder)
            ->when($forAgent, fn ($terms) => $terms->where('visibility', ProcurementNoteVisibilityEnum::AGENT))
            ->each(fn ($term) => $term->loadMissing('attachments'));

        $notes = $this->notes($purchaseOrder)
            ->when($forAgent, fn (Builder $query) => $query->where('procurement_notes.visibility', ProcurementNoteVisibilityEnum::AGENT));

        return [
            'terms'            => SupplierAgreedTermResource::collection($terms)->resolve(),
            'notes'            => ProcurementNoteResource::collection(
                (clone $notes)->where(fn (Builder $query) => $query->where('is_pinned', true)->orWhere('severity', ProcurementNoteSeverityEnum::CRITICAL))
                    ->with('author')->orderByRaw('severity = ? desc', [ProcurementNoteSeverityEnum::CRITICAL->value])->latest('id')->limit(30)->get()
            )->resolve(),
            'open_incidents'   => (clone $notes)->where('category', ProcurementNoteCategoryEnum::INCIDENT)->where('incident_outcome', ProcurementNoteIncidentOutcomeEnum::OPEN)->count(),
            'must_acknowledge' => !$forAgent && $purchaseOrder->state === PurchaseOrderStateEnum::IN_PROCESS && $this->hasCriticalNotes($purchaseOrder),
            'supplier_agreement_status' => $this->supplierAgreement($purchaseOrder),
        ];
    }

    /**
     * Where the order's supplier stands with the Supplier Agreement, and the acceptance the order kept when it was
     * confirmed. Only orders straight to one supplier have it.
     *
     * @return array<string, mixed>|null
     */
    public function supplierAgreement(PurchaseOrder $purchaseOrder): ?array
    {
        if (!$purchaseOrder->supplier_id) {
            return null;
        }

        $status = GetSupplierAgreementStatus::make()->forSuppliers($purchaseOrder->group_id, [$purchaseOrder->supplier_id])[$purchaseOrder->supplier_id] ?? null;
        if (!$status) {
            return null;
        }

        $stamped = $purchaseOrder->supplier_agreement_acceptance_id
            ? SupplierAgreementAcceptance::with('version:id,version')->find($purchaseOrder->supplier_agreement_acceptance_id)
            : null;

        return [
            ...$status,
            'warning'         => $status['status'] !== SupplierAgreementStatusEnum::ACCEPTED->value
                ? __('This supplier has not accepted the current Supplier Agreement. The order can still be sent.')
                : null,
            'order_acceptance' => $stamped ? [
                'version'           => $stamped->version->version,
                'accepted_at'       => $stamped->accepted_at->toIso8601String(),
                'authorised_person' => $stamped->authorised_person,
            ] : null,
        ];
    }

    /**
     * The notes printed on each order's document, keyed by purchase order id, with one read:
     * those marked for the supplier, and for an order placed through an agent those marked for
     * the agent too. Never an internal one.
     *
     * @param  \Illuminate\Support\Collection<int, PurchaseOrder>  $purchaseOrders
     * @return array<int, Collection<int, ProcurementNote>>
     */
    public function documentNotesForMany(\Illuminate\Support\Collection $purchaseOrders): array
    {
        $terms = GetPurchaseOrderAgreedTerms::make();
        $terms->loadSubjects($purchaseOrders);

        $subjects = $purchaseOrders->mapWithKeys(fn (PurchaseOrder $purchaseOrder) => [
            $purchaseOrder->id => $terms->subjects($purchaseOrder)->push($purchaseOrder)->map(fn ($model) => $model->getMorphClass().'|'.$model->id)->all(),
        ]);

        $notes = ProcurementNote::active()
            ->whereIn('visibility', [ProcurementNoteVisibilityEnum::AGENT, ProcurementNoteVisibilityEnum::SUPPLIER])
            ->whereIn(DB::raw("subject_type || '|' || subject_id"), $subjects->flatten()->unique()->values())
            ->orderByDesc('is_pinned')
            ->latest('id')
            ->get();

        return $purchaseOrders->mapWithKeys(function (PurchaseOrder $purchaseOrder) use ($notes, $subjects) {
            $throughAgent = $purchaseOrder->isAgentOrder() || $purchaseOrder->parent instanceof OrgAgent;

            return [$purchaseOrder->id => $notes->filter(fn (ProcurementNote $note) => in_array($note->subject_type.'|'.$note->subject_id, $subjects[$purchaseOrder->id])
                && ($note->organisation_id === null || $note->organisation_id === $purchaseOrder->organisation_id)
                && ($throughAgent || $note->visibility === ProcurementNoteVisibilityEnum::SUPPLIER))->values()];
        })->all();
    }

    /**
     * @return Collection<int, ProcurementNote>
     */
    public function documentNotes(PurchaseOrder $purchaseOrder): Collection
    {
        return $this->documentNotesForMany(collect([$purchaseOrder]))[$purchaseOrder->id];
    }

    public function hasCriticalNotes(PurchaseOrder $purchaseOrder): bool
    {
        return $this->notes($purchaseOrder)->where('severity', ProcurementNoteSeverityEnum::CRITICAL)->exists();
    }

    private function notes(PurchaseOrder $purchaseOrder): Builder
    {
        $subject  = GetPurchaseOrderAgreedTerms::make()->subject($purchaseOrder);
        $agent    = $purchaseOrder->agent;
        $products = $purchaseOrder->purchaseOrderTransactions()->whereNotNull('supplier_product_id')->select('supplier_product_id');

        return ProcurementNote::active()
            ->forOrganisation($purchaseOrder->organisation)
            ->where(function (Builder $query) use ($purchaseOrder, $subject, $agent, $products) {
                $query->where(fn (Builder $query) => $query->where('subject_type', 'PurchaseOrder')->where('subject_id', $purchaseOrder->id))
                    ->orWhere(fn (Builder $query) => $query->where('subject_type', 'SupplierProduct')->whereIn('subject_id', $products));

                foreach (array_filter([$subject, $agent]) as $model) {
                    $query->orWhere(fn (Builder $query) => $query->where('subject_type', $model->getMorphClass())->where('subject_id', $model->id));
                }
            });
    }
}
