<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 7 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\PartnerShippingList;

use App\Actions\OrgAction;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\Permission;
use App\Models\SysAdmin\User;
use App\Notifications\ToProduceItemsCantBeDoneNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;
use OwenIt\Auditing\Events\AuditCustom;

class DismissToProduceItems extends OrgAction
{
    /**
     * Production says a line waiting to be made cannot be made: it leaves the board with the reason,
     * and the buyer's procurement team is told, so they can source it elsewhere.
     *
     * @param  array<int, int>  $ids
     * @return Collection<int, PartnerShoppingListItem>
     */
    public function handle(Production $production, array $ids, string $reason, User $user): Collection
    {
        $sellerId = $production->organisation_id;

        $items = DB::transaction(function () use ($ids, $sellerId, $reason, $user) {
            $items = PartnerShoppingListItem::query()
                ->whereIn('id', $ids)
                ->where('state', ShoppingListItemStateEnum::OPEN)
                ->whereNull('job_order_id')
                ->whereNull('pre_picked_at')
                ->where(function ($query) use ($sellerId) {
                    $query->where('partner_organisation_id', $sellerId)
                        ->orWhere(function ($query) use ($sellerId) {
                            $query->whereNull('partner_organisation_id')->where('organisation_id', $sellerId);
                        });
                })
                ->lockForUpdate()
                ->with('orgStock')
                ->get();

            $items->each(fn (PartnerShoppingListItem $item) => $item->update([
                'state'                => ShoppingListItemStateEnum::DISMISSED,
                'dismiss_reason'       => $reason,
                'dismissed_at'         => now(),
                'dismissed_by_user_id' => $user->id,
                'preparing_at'         => null,
                'quantity_to_produce'  => null,
            ]));

            return $items;
        });

        if ($items->isEmpty()) {
            return $items;
        }

        $who = $user->contact_name ?: $user->username;

        $this->audit($production, __(':user: cannot make :codes. :reason', [
            'user'   => $who,
            'codes'  => $items->map(fn ($item) => $item->orgStock?->code)->filter()->unique()->join(', '),
            'reason' => $reason,
        ]));

        $items->whereNotNull('org_partner_id')->groupBy('org_partner_id')->each(function (Collection $partnerItems, $orgPartnerId) use ($production, $reason, $who, $user) {
            $orgPartner = OrgPartner::find($orgPartnerId);
            if (!$orgPartner) {
                return;
            }

            $note = __(':user (:hub production) cannot make :codes. Reason: :reason', [
                'user'   => $who,
                'hub'    => $production->organisation->code,
                'codes'  => $partnerItems->map(fn ($item) => $item->orgStock?->code.' ×'.trimDecimalZeros($item->quantity))->join(', '),
                'reason' => $reason,
            ]);

            $this->audit($orgPartner->organisation, $note);

            $buyerId = $orgPartner->organisation_id;
            Notification::send(
                User::permission(Permission::whereIn('name', ["procurement.$buyerId", "procurement.$buyerId.edit"])->pluck('name')->all())
                    ->where('status', true)
                    ->where('id', '!=', $user->id)
                    ->get(),
                new ToProduceItemsCantBeDoneNotification($orgPartner, $note)
            );
        });

        return $items;
    }

    private function audit(Model $auditable, string $note): void
    {
        $auditable->auditEvent     = 'cant_be_done';
        $auditable->isCustomEvent  = true;
        $auditable->auditCustomOld = [];
        $auditable->auditCustomNew = ['cant_be_done' => $note];
        Event::dispatch(new AuditCustom($auditable));
        $auditable->isCustomEvent = false;
    }

    public function rules(): array
    {
        return [
            'ids'    => ['required', 'array', 'min:1'],
            'ids.*'  => ['integer'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.orchestrate",
            "productions_operations.{$this->production->id}.prepare",
        ]);
    }

    /**
     * @param  array<int, int>  $ids
     * @return Collection<int, PartnerShoppingListItem>
     */
    public function action(Production $production, array $ids, string $reason, User $user): Collection
    {
        $this->asAction = true;
        $this->initialisationFromProduction($production, ['ids' => $ids, 'reason' => $reason]);

        return $this->handle($production, $this->validatedData['ids'], $this->validatedData['reason'], $user);
    }

    /** @return Collection<int, PartnerShoppingListItem> */
    public function asController(Organisation $organisation, Production $production, ActionRequest $request): Collection
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($production, $this->validatedData['ids'], $this->validatedData['reason'], $request->user());
    }

    public function htmlResponse(Collection $items): RedirectResponse
    {
        return Redirect::back()->with('notification', [
            'status' => 'success',
            'title'  => trans_choice(':count line taken off as cannot be made|:count lines taken off as cannot be made', $items->count(), ['count' => $items->count()]),
        ]);
    }
}
