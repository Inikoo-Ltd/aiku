<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 5 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgPartner;

use App\Actions\OrgAction;
use App\Actions\Procurement\PartnerShoppingListItem\StorePartnerShoppingListItem;
use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\OrgPartner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PreparePartnerShoppingListOrder extends OrgAction
{
    use WithProcurementEditAuthorisation;

    /**
     * Normal ordering from the manufacturing hub, one click: our out, doomed and critical SKOs that the
     * hub makes, worst first, go onto its shopping list (picked with the same rules as a sister company
     * rescue, see GetPartnerStockCoverBuckets). The hub makes what it is asked for, so quantities are
     * not capped by its stock. Lines already on the list are left as they
     * are. With a budget (in our currency, for the whole open list) a line that would go over it is
     * skipped and the next, cheaper ones still get their chance.
     *
     * @param  array<int, string>  $buckets
     *
     * @throws ValidationException
     */
    public function handle(OrgPartner $orgPartner, ?float $budget = null, array $buckets = GetPartnerStockCoverBuckets::DEFAULT_ORDER_BUCKETS, bool $worstOnly = true): int
    {
        $fail = fn (string $message) => throw ValidationException::withMessages(['rescue' => $message]);

        if (!$orgPartner->partner->is_manufacturing_hub) {
            $fail(__('Buy from :partner with a purchase order', ['partner' => $orgPartner->partner->name]));
        }

        return DB::transaction(function () use ($orgPartner, $fail, $budget, $buckets, $worstOnly) {
            OrgPartner::whereKey($orgPartner->id)->lockForUpdate()->first();

            $lines = GetPartnerStockCoverBuckets::make()->rescueLines($orgPartner, $buckets, $worstOnly);
            if (!$lines) {
                $fail(__('Nothing to order from :partner right now', ['partner' => $orgPartner->partner->name]));
            }

            $orgStocks        = OrgStock::whereIn('id', array_column($lines, 'org_stock_id'))->get()->keyBy('id');
            $exchange         = $orgPartner->exchangeToOrgCurrency();
            $added            = 0;
            $skippedForBudget = 0;
            $spent            = round((float) $orgPartner->stats?->open_shopping_list_items_value * $exchange * GetPartnerBuyingPriceFactor::run($orgPartner), 2);

            foreach ($lines as $line) {
                if (!$orgStock = $orgStocks->get($line['org_stock_id'])) {
                    continue;
                }

                if ($budget !== null) {
                    if ($line['cost'] <= 0) {
                        continue;
                    }
                    if ($spent + $line['cost'] > $budget) {
                        $skippedForBudget++;
                        continue;
                    }
                }

                try {
                    StorePartnerShoppingListItem::make()->action($orgPartner, $orgStock, ['quantity' => $line['skos']]);
                    $spent += $line['cost'];
                    $added++;
                } catch (HttpException|ValidationException) {
                    continue;
                }
            }

            if ($added === 0) {
                $fail($skippedForBudget
                    ? __('Nothing more fits in the budget, the shopping list is already at :amount', ['amount' => $orgPartner->organisation->currency->code.' '.number_format($spent, 2)])
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
