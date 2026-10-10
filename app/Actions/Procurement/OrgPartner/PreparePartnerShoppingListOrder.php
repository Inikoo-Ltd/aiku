<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 5 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgPartner;

use App\Actions\OrgAction;
use App\Actions\Procurement\OrgPartner\Hydrators\OrgPartnerHydrateShoppingListItems;
use App\Actions\Procurement\PartnerShoppingListItem\EnsurePartnerOrderPackedInMatches;
use App\Actions\Procurement\PartnerShoppingListItem\RoundPartnerQuantityToBatches;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemPriorityEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Events\BroadcastProductionQueuesChanged;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\OrgPartner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class PreparePartnerShoppingListOrder extends OrgAction
{
    use WithProcurementEditAuthorisation;

    /**
     * Normal ordering from the manufacturing hub, one click: our out, doomed and critical SKOs that the
     * hub makes, worst first, go onto its shopping list (picked with the same rules as a sister company
     * rescue, see GetPartnerStockCoverBuckets). The hub makes what it is asked for, so quantities are
     * not capped by its stock. Lines already on the list are left as they
     * are. With a budget (in our currency, for the lines added this time, so it can be run again and again
     * in small steps) a line that would go over it is skipped and the next, cheaper ones still get their chance.
     * Lines the hub suggests on the buyer's behalf are flagged, and never change a draft already on the list.
     *
     * @param  array<int, string>  $buckets
     *
     * @throws ValidationException
     */
    public function handle(OrgPartner $orgPartner, ?float $budget = null, array $buckets = GetPartnerStockCoverBuckets::DEFAULT_ORDER_BUCKETS, bool $worstOnly = true, bool $suggestedByHub = false): int
    {
        $fail = fn (string $message) => throw ValidationException::withMessages(['rescue' => $message]);

        if (!$orgPartner->partner->is_manufacturing_hub) {
            $fail(__('Buy from :partner with a purchase order', ['partner' => $orgPartner->partner->name]));
        }

        return DB::transaction(function () use ($orgPartner, $fail, $budget, $buckets, $worstOnly, $suggestedByHub) {
            OrgPartner::whereKey($orgPartner->id)->lockForUpdate()->first();

            $lines = GetPartnerStockCoverBuckets::make()->rescueLines($orgPartner, $buckets, $worstOnly);
            if (!$lines) {
                $fail(__('Nothing to order from :partner right now', ['partner' => $orgPartner->partner->name]));
            }

            $orgStocks = OrgStock::whereIn('id', array_column($lines, 'org_stock_id'))
                ->whereIn('organisation_id', [$orgPartner->organisation_id, $orgPartner->partner_id])
                ->get()
                ->keyBy('id');

            $stockIds   = $orgStocks->pluck('stock_id')->unique()->values()->all();
            $mismatched = array_flip(EnsurePartnerOrderPackedInMatches::make()->mismatchedStockIds($orgPartner, $stockIds));
            $quanta     = RoundPartnerQuantityToBatches::quantaByStockId($orgPartner, $stockIds);
            $drafts     = PartnerShoppingListItem::where('org_partner_id', $orgPartner->id)
                ->where('state', ShoppingListItemStateEnum::DRAFT)
                ->whereIn('org_stock_id', $orgStocks->keys())
                ->get()
                ->keyBy('org_stock_id');

            $now              = now();
            $userId           = request()->user()?->id;
            $rows             = [];
            $added            = 0;
            $skippedForBudget = 0;
            $spent            = 0.0;

            foreach ($lines as $line) {
                if (!$orgStock = $orgStocks->get($line['org_stock_id'])) {
                    continue;
                }

                if (isset($mismatched[$orgStock->stock_id])) {
                    continue;
                }

                $draft = $drafts->get($orgStock->id);
                if ($suggestedByHub && $draft) {
                    continue;
                }

                $quantity = RoundPartnerQuantityToBatches::roundUp((float) $line['skos'], $quanta[$orgStock->stock_id] ?? 1);
                $cost     = (float) $line['skos'] > 0 ? round($line['cost'] * $quantity / (float) $line['skos'], 2) : (float) $line['cost'];

                if ($budget !== null) {
                    if ($cost <= 0) {
                        continue;
                    }
                    if ($spent + $cost > $budget) {
                        $skippedForBudget++;
                        continue;
                    }
                }

                if ($draft) {
                    $draft->update(['quantity' => $quantity]);
                } else {
                    $rows[] = [
                        'group_id'                => $orgPartner->group_id,
                        'organisation_id'         => $orgPartner->organisation_id,
                        'org_partner_id'          => $orgPartner->id,
                        'partner_organisation_id' => $orgPartner->partner_id,
                        'org_stock_id'            => $orgStock->id,
                        'stock_id'                => $orgStock->stock_id,
                        'quantity'                => $quantity,
                        'priority'                => ShoppingListItemPriorityEnum::NORMAL->value,
                        'state'                   => ShoppingListItemStateEnum::DRAFT->value,
                        'added_by_user_id'        => $userId,
                        'suggested_by_hub'        => $suggestedByHub,
                        'created_at'              => $now,
                        'updated_at'              => $now,
                    ];
                }

                $spent += $cost;
                $added++;
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                PartnerShoppingListItem::insert($chunk);
            }

            if ($added > 0) {
                BroadcastProductionQueuesChanged::dispatch($orgPartner->partner_id);
                OrgPartnerHydrateShoppingListItems::dispatch($orgPartner);
            }

            if ($added === 0) {
                $fail($skippedForBudget
                    ? __('Nothing fits in a budget of :amount', ['amount' => $orgPartner->organisation->currency->code.' '.number_format($budget, 2)])
                    : __('Everything to order from :partner is already on the shopping list', ['partner' => $orgPartner->partner->name]));
            }

            return $added;
        });
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

    public function asController(OrgPartner $orgPartner, ActionRequest $request): int
    {
        $this->initialisation($orgPartner->organisation, $request);

        $budget = $this->validatedData['budget'] ?? null;

        return $this->handle(
            $orgPartner,
            $budget === null ? null : (float) $budget,
            $this->validatedData['buckets'] ?? GetPartnerStockCoverBuckets::DEFAULT_ORDER_BUCKETS,
            (bool) ($this->validatedData['worst_only'] ?? true)
        );
    }

    public function htmlResponse(int $added, ActionRequest $request): RedirectResponse
    {
        return Redirect::route('grp.org.procurement.org_partners.show.shopping_list.index', [
            $request->route('orgPartner')->organisation->slug,
            $request->route('orgPartner')->id,
        ]);
    }
}
