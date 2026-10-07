<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 7 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Intervention;

use App\Actions\OrgAction;
use App\Actions\Procurement\OrgPartner\GetPartnerStockCoverBuckets;
use App\Actions\Procurement\OrgPartner\PreparePartnerShoppingListOrder;
use App\Models\Procurement\OrgPartner;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\Permission;
use App\Models\SysAdmin\User;
use App\Notifications\InterventionOrderNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use OwenIt\Auditing\Events\AuditCustom;

class PrepareInterventionOrder extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $this->organisation->is_manufacturing_hub && $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            "productions_procurement.{$this->production->id}.edit",
            "productions_operations.{$this->production->id}.orchestrate",
        ]);
    }

    /**
     * The hub's production manager orders from the hub on a buyer's behalf: the same lines the buyer's own
     * "Prepare order" picks go onto the buyer's ongoing PO as drafts flagged as the hub's suggestion, so the
     * buyer keeps the last word. The buyer's procurement team is told, and the order is written in the
     * history of the buyer and of the production.
     *
     * @param  array<int, string>  $buckets
     *
     * @throws ValidationException
     */
    public function handle(Production $production, OrgPartner $orgPartner, User $user, ?float $budget, array $buckets, bool $worstOnly): int
    {
        $added = PreparePartnerShoppingListOrder::make()->handle($orgPartner, $budget, $buckets, $worstOnly, suggestedByHub: true);

        $note = __(':user (:hub production) added :count lines to the ongoing PO on behalf of :buyer', [
            'user'  => $user->contact_name ?: $user->username,
            'hub'   => $orgPartner->partner->code,
            'count' => $added,
            'buyer' => $orgPartner->organisation->name,
        ]);

        foreach ([$orgPartner->organisation, $production] as $auditable) {
            $auditable->auditEvent     = 'order_on_behalf';
            $auditable->isCustomEvent  = true;
            $auditable->auditCustomOld = [];
            $auditable->auditCustomNew = ['order_on_behalf' => $note];
            Event::dispatch(new AuditCustom($auditable));
            $auditable->isCustomEvent = false;
        }

        $buyerId = $orgPartner->organisation_id;
        Notification::send(
            User::permission(Permission::whereIn('name', ["procurement.$buyerId", "procurement.$buyerId.edit"])->pluck('name')->all())
                ->where('status', true)
                ->where('id', '!=', $user->id)
                ->get(),
            new InterventionOrderNotification($orgPartner, $note)
        );

        return $added;
    }

    public function rules(): array
    {
        return [
            'budget'     => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'buckets'    => ['sometimes', 'array', 'min:1'],
            'buckets.*'  => ['string', Rule::in(['out', 'w1', 'w2', 'w3'])],
            'worst_only' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @throws ValidationException
     */
    public function asController(Organisation $organisation, Production $production, OrgPartner $orgPartner, ActionRequest $request): int
    {
        abort_unless($orgPartner->partner_id === $production->organisation_id && $orgPartner->organisation_id !== $production->organisation_id, 404);
        $this->initialisationFromProduction($production, $request);

        $budget = $this->validatedData['budget'] ?? null;

        return $this->handle(
            $production,
            $orgPartner,
            $request->user(),
            $budget === null ? null : (float) $budget,
            $this->validatedData['buckets'] ?? GetPartnerStockCoverBuckets::DEFAULT_ORDER_BUCKETS,
            (bool) ($this->validatedData['worst_only'] ?? true)
        );
    }

    public function htmlResponse(int $added): RedirectResponse
    {
        return Redirect::back()->with('notification', [
            'status'      => 'success',
            'title'       => __('Order prepared'),
            'description' => trans_choice('{1} 1 line added to their ongoing PO|[2,*] :count lines added to their ongoing PO', $added),
        ]);
    }
}
