<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-12 12:06
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\PurchaseOrder\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrderReference;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;

/**
 * Checks a new reference while it is typed in the purchase order heading: whether another order of the organisation
 * already uses it, the slug (URL) it would give, free variants when it is taken, and orders with a similar reference.
 * An agent sees only the orders placed through it.
 */
class GetPurchaseOrderReferenceAvailability extends OrgAction
{
    private const float MIN_SIMILARITY = 0.3;

    private const int SIMILAR_LIMIT = 8;

    private const int ALTERNATIVES_LIMIT = 3;

    private PurchaseOrder $purchaseOrder;

    private ?Organisation $agentOrganisation = null;

    public function handle(PurchaseOrder $purchaseOrder, string $reference): array
    {
        $conflict = UpdatePurchaseOrderReference::conflictingPurchaseOrder($purchaseOrder, $reference);

        return [
            'reference'    => $reference,
            'is_unchanged' => $reference === $purchaseOrder->reference,
            'is_available' => $conflict === null && Str::slug($reference) !== '',
            'conflict'     => $conflict ? $this->purchaseOrderData($conflict) : null,
            'slug'         => Str::slug($reference) === '' ? null : $this->previewSlug($purchaseOrder, $reference),
            'alternatives' => $conflict ? $this->alternatives($purchaseOrder, $reference) : [],
            'similar'      => $this->similar($purchaseOrder, $reference),
        ];
    }

    private function previewSlug(PurchaseOrder $purchaseOrder, string $reference): string
    {
        $preview            = clone $purchaseOrder;
        $preview->reference = $reference;
        $preview->generateSlug();

        return $preview->slug;
    }

    private function alternatives(PurchaseOrder $purchaseOrder, string $reference): array
    {
        $alternatives = [];
        $suffix       = 2;

        while (count($alternatives) < self::ALTERNATIVES_LIMIT && $suffix < 50) {
            $candidate = "$reference-$suffix";
            if (UpdatePurchaseOrderReference::conflictingPurchaseOrder($purchaseOrder, $candidate) === null) {
                $alternatives[] = $candidate;
            }
            $suffix++;
        }

        return $alternatives;
    }

    private function similar(PurchaseOrder $purchaseOrder, string $reference): array
    {
        return PurchaseOrder::query()
            ->where('organisation_id', $purchaseOrder->organisation_id)
            ->where('id', '!=', $purchaseOrder->id)
            ->when($this->agentOrganisation, fn ($query) => $query->where('agent_id', $purchaseOrder->agent_id))
            ->whereRaw('lower(reference) != lower(?)', [$reference])
            ->whereRaw('similarity(reference, ?) >= ?', [$reference, self::MIN_SIMILARITY])
            ->orderByRaw('similarity(reference, ?) desc', [$reference])
            ->limit(self::SIMILAR_LIMIT)
            ->get()
            ->map(fn (PurchaseOrder $similar) => $this->purchaseOrderData($similar))
            ->all();
    }

    private function purchaseOrderData(PurchaseOrder $purchaseOrder): array
    {
        return [
            'reference'  => $purchaseOrder->reference,
            'state'      => $purchaseOrder->trashed() ? __('Deleted') : $purchaseOrder->displayState()['label'],
            'is_deleted' => $purchaseOrder->trashed(),
            'url'        => $purchaseOrder->trashed() || !$this->isVisible($purchaseOrder) ? null : $this->url($purchaseOrder),
        ];
    }

    private function isVisible(PurchaseOrder $purchaseOrder): bool
    {
        return !$this->agentOrganisation || $purchaseOrder->agent_id === $this->purchaseOrder->agent_id;
    }

    private function url(PurchaseOrder $purchaseOrder): string
    {
        if ($this->agentOrganisation) {
            return route('grp.org.agent.purchase_orders.show', [$this->agentOrganisation->slug, $purchaseOrder->slug]);
        }

        return route('grp.org.procurement.purchase_orders.show', [$purchaseOrder->organisation->slug, $purchaseOrder->slug]);
    }

    public function authorize(ActionRequest $request): bool
    {
        return UpdatePurchaseOrderReference::canRename($this->purchaseOrder, $request);
    }

    public function rules(): array
    {
        return [
            'reference' => ['required', 'string', 'max:255'],
        ];
    }

    public function asController(PurchaseOrder $purchaseOrder, ActionRequest $request): array
    {
        $this->purchaseOrder = $purchaseOrder;

        if (!$request->user()->authTo("procurement.{$purchaseOrder->organisation_id}.edit")) {
            $this->agentOrganisation = Organisation::find(UpdatePurchaseOrderReference::agentOrganisationId($purchaseOrder));
        }

        $this->initialisation($purchaseOrder->organisation, $request);

        return $this->handle($purchaseOrder, $this->validatedData['reference']);
    }
}
