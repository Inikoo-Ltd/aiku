<?php

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\GoodsIn\StockDelivery\Traits\HasStockDeliveryHydrators;
use App\Actions\GoodsIn\StockDeliveryItem\Traits\WithStockDeliveryItemStatePropagation;
use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\NotifyBuyerOfAgentStageChange;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\SupplyChain\AgentInvoice\StoreAgentInvoice;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\SupplyChain\StockDeliveryInvoice\StockDeliveryInvoiceSourceEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderAttachmentScopeEnum;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryTypeEnum;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use OwenIt\Auditing\Events\AuditCustom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class DispatchStockDelivery extends OrgAction
{
    use AsAction;
    use HasStockDeliveryHydrators;
    use WithActionUpdate;
    use WithStockDeliveryItemStatePropagation;

    public int $hydratorsDelay = 0;

    private StockDelivery $stockDelivery;

    private const DISPATCHABLE_STATES = [
        StockDeliveryStateEnum::IN_PROCESS,
        StockDeliveryStateEnum::CONFIRMED,
        StockDeliveryStateEnum::READY_TO_SHIP,
    ];

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction || $request->user()->authTo("procurement.{$this->organisation->id}.edit")) {
            return true;
        }

        $agentOrganisationId = $this->stockDelivery->agent?->organisation_id;

        return $agentOrganisationId && $request->user()->authTo("procurement.$agentOrganisationId.edit");
    }

    public static function buyersManageAgentDeliveries(StockDelivery $stockDelivery): bool
    {
        return (bool) Arr::get($stockDelivery->agent?->settings, 'buyers_manage_stock_deliveries', false);
    }

    /**
     * Documents a delivery ships with: always the packing list, and the bill of lading for a full or shared container.
     *
     * @return string[]
     */
    public static function missingEvidence(StockDelivery $stockDelivery): array
    {
        $required = [PurchaseOrderAttachmentScopeEnum::PACKING_LIST];
        if (StockDeliveryTypeEnum::tryFrom((string) Arr::get($stockDelivery->data, 'delivery_type'))?->sailsWithBillOfLading()) {
            $required[] = PurchaseOrderAttachmentScopeEnum::BILL_OF_LADING;
        }

        $uploaded = $stockDelivery->attachments()->get()->pluck('pivot.scope')->all();

        return collect($required)
            ->reject(fn (PurchaseOrderAttachmentScopeEnum $scope) => in_array($scope->value, $uploaded))
            ->map(fn (PurchaseOrderAttachmentScopeEnum $scope) => PurchaseOrderAttachmentScopeEnum::labels()[$scope->value])
            ->values()
            ->all();
    }

    public function rules(): array
    {
        return [
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if (!$this->asAction && !$this->stockDelivery->isManagedByPartner() && !trim((string) $this->get('reason'))
            && $missing = self::missingEvidence($this->stockDelivery)) {
            $validator->errors()->add('reason', __('Upload the :documents, or give a reason to dispatch without them', ['documents' => implode(', ', $missing)]));
        }
        if (!$this->asAction && $this->stockDelivery->isManagedByPartner()) {
            $validator->errors()->add('state', __('This delivery is managed by the partner until you receive it'));
        }
        if (!$this->asAction && $this->stockDelivery->agent_id) {
            $byBuyer      = request()->user()->authTo("procurement.{$this->organisation->id}.edit");
            $agentInvoice = $this->stockDelivery->agentInvoice()->first();
            $validSources = $byBuyer
                ? [StockDeliveryInvoiceSourceEnum::AGENT, StockDeliveryInvoiceSourceEnum::ACTUAL]
                : [StockDeliveryInvoiceSourceEnum::AGENT];

            if ($byBuyer && !self::buyersManageAgentDeliveries($this->stockDelivery)) {
                $validator->errors()->add('state', __('Only the agent can mark this container as dispatched'));
            } elseif (!in_array($agentInvoice?->source, $validSources, true)) {
                $validator->errors()->add('invoice', __('Make the invoice of this container before dispatching it'));
            } elseif ($agentInvoice->source === StockDeliveryInvoiceSourceEnum::AGENT && !StoreAgentInvoice::matchesContainer($agentInvoice, $this->stockDelivery)) {
                $validator->errors()->add('invoice', __('The container changed after its invoice was made, make the invoice again before dispatching it'));
            }
        }
        if (!in_array($this->stockDelivery->state, self::DISPATCHABLE_STATES, true)) {
            $validator->errors()->add('state', __('You can not dispatch this stock delivery with state :state', ['state' => $this->stockDelivery->state->value]));
        }
    }

    public function handle(StockDelivery $stockDelivery, ?string $reason = null): StockDelivery
    {
        $stockDelivery->update([
            'state'         => StockDeliveryStateEnum::DISPATCHED,
            'dispatched_at' => now(),
        ]);

        if ($reason) {
            $stockDelivery->auditEvent     = 'stage_override';
            $stockDelivery->isCustomEvent  = true;
            $stockDelivery->auditCustomOld = ['missing_documents' => implode(', ', self::missingEvidence($stockDelivery))];
            $stockDelivery->auditCustomNew = ['dispatched_at' => $stockDelivery->dispatched_at->toDateString(), 'reason' => $reason];
            Event::dispatch(new AuditCustom($stockDelivery));
            $stockDelivery->isCustomEvent  = false;
            $stockDelivery->auditCustomOld = $stockDelivery->auditCustomNew = [];
        }

        $stockDelivery->items()
            ->where('state', '!=', StockDeliveryItemStateEnum::CANCELLED)
            ->get()
            ->each(fn (StockDeliveryItem $stockDeliveryItem) => $this->dispatchItem($stockDeliveryItem));

        UpdatePurchaseOrdersDeliveryStateFromStockDelivery::run($stockDelivery);

        $this->runStockDeliveryHydrators($stockDelivery);

        return $stockDelivery;
    }

    private function dispatchItem(StockDeliveryItem $stockDeliveryItem): void
    {
        if ($stockDeliveryItem->state === StockDeliveryItemStateEnum::DISPATCHED) {
            return;
        }

        $stockDeliveryItem = $this->update($stockDeliveryItem, [
            'state' => StockDeliveryItemStateEnum::DISPATCHED,
            'data'  => ['dispatched_at' => now()->toIso8601String()],
        ], ['data']);

        $this->propagateStockDeliveryItemStateChange($stockDeliveryItem);
    }

    public function asController(StockDelivery $stockDelivery, ActionRequest $request): StockDelivery
    {
        $this->stockDelivery = $stockDelivery;
        $this->initialisation($stockDelivery->organisation, $request);

        $stockDelivery = $this->handle($stockDelivery, trim((string) ($this->validatedData['reason'] ?? '')) ?: null);

        foreach ($stockDelivery->purchaseOrders()->get() as $purchaseOrder) {
            NotifyBuyerOfAgentStageChange::run($purchaseOrder, $request->user(), __('shipped in :delivery', ['delivery' => $stockDelivery->reference]));
        }

        return $stockDelivery;
    }

    public function action(StockDelivery $stockDelivery): StockDelivery
    {
        $this->asAction      = true;
        $this->stockDelivery = $stockDelivery;
        $this->initialisation($stockDelivery->organisation, []);

        return $this->handle($stockDelivery);
    }

    public function htmlResponse(StockDelivery $stockDelivery): RedirectResponse
    {
        return redirect()->back();
    }
}
