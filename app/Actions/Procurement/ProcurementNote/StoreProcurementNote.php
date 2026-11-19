<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\ProcurementNote;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Helpers\Media\SaveModelAttachment;
use App\Actions\OrgAction;
use App\Actions\Procurement\WithAgreedTermsAuthorisation;
use App\Enums\Procurement\ProcurementNote\ProcurementNoteCategoryEnum;
use App\Enums\Procurement\ProcurementNote\ProcurementNoteIncidentOutcomeEnum;
use App\Enums\Procurement\ProcurementNote\ProcurementNoteIncidentTypeEnum;
use App\Enums\Procurement\ProcurementNote\ProcurementNoteSeverityEnum;
use App\Enums\Procurement\ProcurementNote\ProcurementNoteStateEnum;
use App\Enums\Procurement\ProcurementNote\ProcurementNoteVisibilityEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Helpers\Currency;
use App\Models\Procurement\ProcurementNote;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreProcurementNote extends OrgAction
{
    use WithAgreedTermsAuthorisation;

    private ?int $documentOrganisationId = null;

    private ?Supplier $supplier = null;

    public function handle(Supplier|SupplierProduct|Agent|PurchaseOrder|StockDelivery $subject, array $modelData): ProcurementNote
    {
        $attachments = Arr::pull($modelData, 'attachments', []);
        $sourceId    = Arr::get($modelData, 'source_id');

        if (Arr::pull($modelData, 'strikethrough')) {
            $modelData['state'] = ProcurementNoteStateEnum::SUPERSEDED;
        }

        if (Arr::get($modelData, 'category') === ProcurementNoteCategoryEnum::INCIDENT->value) {
            $modelData['incident_outcome'] ??= ProcurementNoteIncidentOutcomeEnum::OPEN->value;
            if ($subject instanceof PurchaseOrder) {
                $modelData['purchase_order_id'] ??= $subject->id;
            }
        }

        if ($subject instanceof PurchaseOrder || $subject instanceof StockDelivery) {
            $modelData['organisation_id'] = $subject->organisation_id;
        }

        if (Arr::get($modelData, 'incident_amount') !== null && Arr::get($modelData, 'incident_currency_id')) {
            $exchange                         = GetCurrencyExchange::run(Currency::find($modelData['incident_currency_id']), $subject->group->currency);
            $modelData['grp_incident_amount'] = $exchange ? round($modelData['incident_amount'] * $exchange, 2) : null;
        }

        $note = ProcurementNote::create(array_merge($modelData, [
            'group_id'       => $subject->group_id,
            'subject_type'   => $subject->getMorphClass(),
            'subject_id'     => $subject->id,
            'author_user_id' => Arr::get($modelData, 'author_user_id', request()->user()?->id),
            'fetched_at'     => $sourceId ? now() : null,
        ]));

        foreach ($attachments as $file) {
            SaveModelAttachment::make()->action($note, [
                'path'         => $file->getPathName(),
                'originalName' => $file->getClientOriginalName(),
                'extension'    => $file->getClientOriginalExtension(),
                'scope'        => 'Evidence',
                'caption'      => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            ]);
        }

        return $note;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $this->canWriteProcurementNotes($request->user(), $this->group, $this->documentOrganisationId ?? ($request->integer('organisation_id') ?: null))
            || ($request->input('category') === ProcurementNoteCategoryEnum::INCIDENT->value && $this->canReportIncidents($request->user(), $this->documentOrganisationId))
            || ($this->supplier && $request->input('visibility') === ProcurementNoteVisibilityEnum::AGENT->value
                && $this->canWriteAgentSupplierNotes($request->user(), $this->supplier, $request->integer('organisation_id') ?: null));
    }

    public function rules(): array
    {
        $rules = [
            'title'                => ['sometimes', 'nullable', 'string', 'max:255'],
            'body'                 => ['required_without:title', 'nullable', 'string', 'max:10000'],
            'category'             => ['sometimes', Rule::enum(ProcurementNoteCategoryEnum::class)],
            'severity'             => ['sometimes', Rule::enum(ProcurementNoteSeverityEnum::class)],
            'visibility'           => ['sometimes', Rule::enum(ProcurementNoteVisibilityEnum::class)],
            'is_pinned'            => ['sometimes', 'boolean'],
            'organisation_id'      => ['sometimes', 'nullable', 'integer', Rule::exists('organisations', 'id')->where('group_id', $this->group->id)],
            'purchase_order_id'    => ['sometimes', 'nullable', 'integer', Rule::exists('purchase_orders', 'id')->where('group_id', $this->group->id)],
            'incident_amount'      => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'incident_currency_id' => ['sometimes', 'nullable', 'required_with:incident_amount', 'integer', 'exists:currencies,id'],
            'incident_outcome'     => ['sometimes', 'nullable', Rule::enum(ProcurementNoteIncidentOutcomeEnum::class)],
            'incident_type'        => ['sometimes', 'nullable', Rule::enum(ProcurementNoteIncidentTypeEnum::class)],
            'incident_quantity'    => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'evidence_message_id'  => ['sometimes', 'nullable', 'integer', Rule::exists('supplier_messages', 'id')->where('group_id', $this->group->id)],
            'attachments'          => ['sometimes', 'array'],
            'attachments.*'        => ['file', 'max:50000'],
        ];

        if (!$this->strict) {
            $rules['author_user_id'] = ['sometimes', 'nullable', 'integer'];
            $rules['author_name']    = ['sometimes', 'nullable', 'string'];
            $rules['strikethrough']  = ['sometimes', 'boolean'];
            $rules['source_id']      = ['sometimes', 'string'];
            $rules['created_at']     = ['sometimes', 'date'];
        }

        return $rules;
    }

    public function action(Supplier|SupplierProduct|Agent|PurchaseOrder|StockDelivery $subject, array $modelData, bool $strict = true): ProcurementNote
    {
        $this->asAction = true;
        $this->strict   = $strict;
        $this->initialisationFromGroup($subject->group, $modelData);

        return $this->handle($subject, $this->validatedData);
    }

    public function inPurchaseOrder(PurchaseOrder $purchaseOrder, ActionRequest $request): void
    {
        $this->documentOrganisationId = $purchaseOrder->organisation_id;
        $this->storeFromRequest($purchaseOrder, $request);
    }

    public function inStockDelivery(StockDelivery $stockDelivery, ActionRequest $request): void
    {
        $this->documentOrganisationId = $stockDelivery->organisation_id;
        $this->storeFromRequest($stockDelivery, $request);
    }

    public function inSupplier(Supplier $supplier, ActionRequest $request): void
    {
        $this->supplier = $supplier;
        $this->storeFromRequest($supplier, $request);
    }

    public function inSupplierProduct(SupplierProduct $supplierProduct, ActionRequest $request): void
    {
        $this->storeFromRequest($supplierProduct, $request);
    }

    public function inAgent(Agent $agent, ActionRequest $request): void
    {
        $this->storeFromRequest($agent, $request);
    }

    private function storeFromRequest(Supplier|SupplierProduct|Agent|PurchaseOrder|StockDelivery $subject, ActionRequest $request): void
    {
        $this->initialisationFromGroup($subject->group, $request);
        $this->handle($subject, $this->validatedData);
    }
}
