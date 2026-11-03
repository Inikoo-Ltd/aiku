<?php

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\GoodsIn\StockDelivery\Traits\HasStockDeliveryHydrators;
use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\Hydrators\PurchaseOrderHydrateDeliveryState;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\SysAdmin\Organisation;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use OwenIt\Auditing\Events\AuditCustom;

class DeleteStockDelivery extends OrgAction
{
    use AsAction;
    use HasStockDeliveryHydrators;

    private StockDelivery $stockDelivery;

    private ?Organisation $agentOrganisation = null;

    private const DELETABLE_STATES = [
        StockDeliveryStateEnum::IN_PROCESS,
        StockDeliveryStateEnum::CONFIRMED,
        StockDeliveryStateEnum::READY_TO_SHIP,
    ];

    private const AGENT_DELIVERABLE_STATES = [
        StockDeliveryStateEnum::IN_PROCESS,
        StockDeliveryStateEnum::CONFIRMED,
        StockDeliveryStateEnum::READY_TO_SHIP,
        StockDeliveryStateEnum::DISPATCHED,
    ];

    public const RESPONSIBILITY_PHRASE = 'I accept responsibility';

    public const MIN_REASON_LENGTH = 5;

    public static function isAgentDelivery(StockDelivery $stockDelivery): bool
    {
        return $stockDelivery->parent_type === 'OrgAgent';
    }

    /**
     * A container still in process has not moved yet, so its reason is enough without the typed phrase.
     */
    public static function needsResponsibility(StockDelivery $stockDelivery): bool
    {
        return self::isAgentDelivery($stockDelivery) && $stockDelivery->state !== StockDeliveryStateEnum::IN_PROCESS;
    }

    public static function acceptsResponsibility(?string $typed): bool
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) $typed), " .\t\n")) === mb_strtolower(self::RESPONSIBILITY_PHRASE);
    }

    /**
     * Why an agent's container can not be deleted, or null when it can: only before it is received, since receiving
     * settles its purchase orders, and never while money, costs or claims are recorded against it.
     */
    public static function agentDeliveryRefusal(StockDelivery $stockDelivery): ?string
    {
        if (!in_array($stockDelivery->state, self::AGENT_DELIVERABLE_STATES, true) || $stockDelivery->items()->where('unit_quantity_placed', '>', 0)->exists()) {
            return __(':reference is already received, unreceive it before deleting it', ['reference' => $stockDelivery->reference]);
        }

        return self::recordsRefusal($stockDelivery);
    }

    /**
     * Money, costs and claims recorded against a delivery, whatever its state, that a delete would leave behind.
     */
    public static function recordsRefusal(StockDelivery $stockDelivery): ?string
    {
        return match (true) {
            $stockDelivery->agentLedgerEntries()->exists(),
            $stockDelivery->supplierPayments()->exists(),
            $stockDelivery->depositApplications()->exists()                                                => __(':reference has payments or deposits, remove them first', ['reference' => $stockDelivery->reference]),
            $stockDelivery->claims()->exists()                                                             => __(':reference has claims, remove them first', ['reference' => $stockDelivery->reference]),
            $stockDelivery->costs()->whereNotNull('amount')->where('amount', '!=', 0)->exists(),
            $stockDelivery->serviceInvoices()->exists()                                                    => __(':reference has costs or service invoices, remove them first', ['reference' => $stockDelivery->reference]),
            default                                                                                        => null,
        };
    }

    /**
     * The organisation's procurement staff, or the agent's staff for the agent's own containers.
     */
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction || $request->user()->authTo("procurement.{$this->organisation->id}.edit")) {
            return true;
        }

        $agentOrganisation = self::isAgentDelivery($this->stockDelivery) ? $this->stockDelivery->agent?->organisation : null;
        if ($agentOrganisation && $request->user()->authTo("procurement.$agentOrganisation->id.edit")) {
            $this->agentOrganisation = $agentOrganisation;

            return true;
        }

        return false;
    }

    public function rules(): array
    {
        return [
            'responsibility' => ['sometimes', 'nullable', 'string', 'max:255'],
            'reason'         => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Keeps why it was deleted on the row and in its history.
     */
    public static function recordDeletion(StockDelivery|PurchaseOrder $model, string $reason): void
    {
        $model->update(['data' => [...($model->data ?? []), 'deletion' => ['reason' => $reason, 'user_id' => auth()->id(), 'deleted_at' => now()->toIso8601String()]]]);

        $model->auditEvent     = 'deleted';
        $model->isCustomEvent  = true;
        $model->auditCustomOld = [];
        $model->auditCustomNew = ['deletion_reason' => $reason];
        Event::dispatch(new AuditCustom($model));
        $model->isCustomEvent = false;
    }

    public function afterValidator(Validator $validator): void
    {
        if (!$this->asAction && $this->stockDelivery->isManagedByPartner()) {
            $validator->errors()->add('state', __('This delivery is managed by the partner until you receive it'));
        }

        if (self::isAgentDelivery($this->stockDelivery)) {
            if ($refusal = self::agentDeliveryRefusal($this->stockDelivery)) {
                $validator->errors()->add('state', $refusal);
            }
            if ($this->agentOrganisation && $this->stockDelivery->state !== StockDeliveryStateEnum::IN_PROCESS) {
                $validator->errors()->add('state', __('The agent can delete a container only while it is in process'));
            }
            if (!$this->asAction && self::needsResponsibility($this->stockDelivery) && !self::acceptsResponsibility($this->get('responsibility'))) {
                $validator->errors()->add('responsibility', __('Type ":phrase" to delete it', ['phrase' => self::RESPONSIBILITY_PHRASE]));
            }
            if (!$this->asAction && mb_strlen(trim((string) $this->get('reason'))) < self::MIN_REASON_LENGTH) {
                $validator->errors()->add('reason', __('Say why you are deleting it'));
            }

            return;
        }

        if (!in_array($this->stockDelivery->state, self::DELETABLE_STATES, true)) {
            $validator->errors()->add('state', __('You can not delete this stock delivery with state :state', ['state' => $this->stockDelivery->state->value]));
        }
    }

    public function handle(StockDelivery $stockDelivery, ?string $reason = null): ?PurchaseOrder
    {
        if ($reason) {
            self::recordDeletion($stockDelivery, $reason);
        }

        $purchaseOrders        = $stockDelivery->purchaseOrders()->get();
        $redirectPurchaseOrder = $purchaseOrders->first();

        $stockDelivery->items()->delete();
        $stockDelivery->purchaseOrders()->detach();
        $stockDelivery->delete();

        foreach ($purchaseOrders as $purchaseOrder) {
            PurchaseOrderHydrateDeliveryState::run($purchaseOrder);
        }

        $this->runStockDeliveryHydrators($stockDelivery);

        return $redirectPurchaseOrder;
    }

    public function asController(StockDelivery $stockDelivery, ActionRequest $request): ?PurchaseOrder
    {
        $this->stockDelivery = $stockDelivery;
        $this->initialisation($stockDelivery->organisation, $request);

        return $this->handle($stockDelivery, trim((string) $this->get('reason')) ?: null);
    }

    public function action(StockDelivery $stockDelivery, ?string $reason = null): ?PurchaseOrder
    {
        $this->asAction      = true;
        $this->stockDelivery = $stockDelivery;
        $this->initialisation($stockDelivery->organisation, []);

        return $this->handle($stockDelivery, $reason);
    }

    public function htmlResponse(?PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if ($this->agentOrganisation) {
            return redirect()->route('grp.org.agent.stock_deliveries.index', ['organisation' => $this->agentOrganisation->slug]);
        }

        if (!$purchaseOrder) {
            return redirect()->back();
        }

        return redirect()->route('grp.org.procurement.purchase_orders.show', [
            'organisation'  => $purchaseOrder->organisation->slug,
            'purchaseOrder' => $purchaseOrder->slug,
        ]);
    }
}
