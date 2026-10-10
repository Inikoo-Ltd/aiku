<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 6 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PartnerShoppingListItem;

use App\Actions\OrgAction;
use App\Actions\Procurement\OrgPartner\Hydrators\OrgPartnerHydrateShoppingListItems;
use App\Events\BroadcastProductionQueuesChanged;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class SubmitPartnerShoppingList extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    /**
     * The drafts go to the seller's pool. A draft for an SKO the seller already has an untouched open
     * line for is added to that line, so the seller still sees one line per SKO.
     * A promoted draft is stamped with the submission time, so it queues from the moment it was sent.
     * With an item, only that draft is sent and the rest stay on the ongoing PO.
     *
     * @throws ValidationException
     */
    public function handle(OrgPartner $orgPartner, ?PartnerShoppingListItem $onlyItem = null): int
    {
        $submitted = DB::transaction(function () use ($orgPartner, $onlyItem) {
            OrgPartner::whereKey($orgPartner->id)->lockForUpdate()->first();

            $mergeTargets = PartnerShoppingListItem::query()
                ->where('org_partner_id', $orgPartner->id)
                ->where('state', ShoppingListItemStateEnum::OPEN)
                ->whereNull('job_order_id')
                ->whereNull('pre_picked_at')
                ->whereNull('preparing_at')
                ->orderBy('id')
                ->get()
                ->unique('org_stock_id')
                ->keyBy('org_stock_id');

            $merged = 0;

            if ($mergeTargets->isNotEmpty()) {
                $mergeableDrafts = PartnerShoppingListItem::query()
                    ->where('org_partner_id', $orgPartner->id)
                    ->where('state', ShoppingListItemStateEnum::DRAFT)
                    ->whereIn('org_stock_id', $mergeTargets->keys())
                    ->when($onlyItem, fn ($query) => $query->whereKey($onlyItem->id))
                    ->get();

                foreach ($mergeableDrafts as $draft) {
                    $openLine = $mergeTargets->get($draft->org_stock_id);
                    $openLine->update(['quantity' => (float) $openLine->quantity + (float) $draft->quantity]);
                    $draft->delete();
                    $merged++;
                }
            }

            $promoted = PartnerShoppingListItem::query()
                ->where('org_partner_id', $orgPartner->id)
                ->where('state', ShoppingListItemStateEnum::DRAFT)
                ->when($onlyItem, fn ($query) => $query->whereKey($onlyItem->id))
                ->update(['state' => ShoppingListItemStateEnum::OPEN, 'created_at' => now(), 'updated_at' => now()]);

            if ($promoted > 0) {
                BroadcastProductionQueuesChanged::dispatch($orgPartner->partner_id);
            }

            return $merged + $promoted;
        });

        if ($submitted === 0) {
            throw ValidationException::withMessages(['submit' => __('There is nothing to submit')]);
        }

        OrgPartnerHydrateShoppingListItems::dispatch($orgPartner);

        return $submitted;
    }

    public function asController(Organisation $organisation, OrgPartner $orgPartner, ActionRequest $request): int
    {
        abort_unless($orgPartner->organisation_id === $organisation->id, 404);
        $this->initialisation($organisation, $request);

        return $this->handle($orgPartner);
    }

    public function inItem(Organisation $organisation, OrgPartner $orgPartner, PartnerShoppingListItem $partnerShoppingListItem, ActionRequest $request): int
    {
        abort_unless($orgPartner->organisation_id === $organisation->id && $partnerShoppingListItem->org_partner_id === $orgPartner->id, 404);
        $this->initialisation($organisation, $request);

        return $this->handle($orgPartner, $partnerShoppingListItem);
    }

    public function action(OrgPartner $orgPartner, ?PartnerShoppingListItem $onlyItem = null): int
    {
        $this->asAction = true;
        $this->initialisation($orgPartner->organisation, []);

        return $this->handle($orgPartner, $onlyItem);
    }

    public function htmlResponse(int $submitted, ActionRequest $request): RedirectResponse
    {
        if ($request->route('partnerShoppingListItem')) {
            return Redirect::back();
        }

        return Redirect::route('grp.org.procurement.org_partners.show.shopping_list.sent', [
            $request->route('organisation')->slug,
            $request->route('orgPartner')->id,
        ]);
    }
}
