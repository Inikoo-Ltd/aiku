<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 11 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\GoodsIn\StockDelivery\DeleteStockDeliveryCost;
use App\Actions\GoodsIn\StockDelivery\StoreStockDeliveryCost;
use App\Actions\GoodsIn\StockDelivery\UpdateStockDeliveryCost;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryCostTypeEnum;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryServiceInvoiceTypeEnum;
use App\Enums\SysAdmin\Authorisation\OrganisationPermissionsEnum;
use App\Enums\SysAdmin\McpChange\McpChangeTypeEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryCost;
use App\Models\Helpers\Currency;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

/**
 * Landed costs are gated by can_use_mcp_costing on the user's account, on top of the procurement or
 * accounting edit permission the same change needs in the UI. Costs are saved through the normal
 * stock delivery cost actions, so entering the last cost completes the costing exactly as it does in Aiku.
 */
#[Description('Shows or saves the landed costs of a stock delivery: shipping, duty and extra expenses (freight, local transport, customs clearance, fines and so on), which decide the value of the stock put away. Without costs it shows the delivery: its state, currencies, whether it is costed, the value of its goods in the organisation currency, every cost row with amount, currency, note, whether it is not applicable, waiting for its invoice or an estimate, and which costs are still missing. With costs it saves them: each entry has a kind (shipping, duty or one of the extra kinds the tool lists), an amount in a currency (the organisation currency unless given), an optional note, or is marked not applicable, or waiting for its invoice (with an amount it is an estimate, with the days until the invoice is expected). An extra expense without an id is added; with its id it is changed or, with remove, deleted. An anti_dumping extra goes only to the lines of the tariff codes picked for it, which the user picks on the delivery in Aiku (shown here as tariff_codes); until they are picked the costing stays open. A delivery that is already costed and rows that come from service invoices are refused: they are changed in Aiku. A partner delivery takes only its local costs: its goods are costed from the partner order. Local costs above 100% of the value of the goods are refused unless allow_large_changes is true, to catch an amount typed in the wrong currency. Only save after showing the user the costs before and after and they confirmed in their own words, passing their request text. Every save is logged in AI changes and can be reverted there until the delivery is costed. Only for users enrolled to cost deliveries.')]
class StockDeliveryCostsTool extends Tool
{
    use WithMcpPermissions;
    use WithMcpChangeLog;

    public const int MAX_COSTS = 30;

    public function handle(Request $request): Response
    {
        $extraKinds = array_column(StockDeliveryServiceInvoiceTypeEnum::extraOptions(), 'value');

        $request->validate([
            'organisation'                      => ['required', 'string'],
            'stock_delivery'                    => ['required', 'string'],
            'costs'                             => ['sometimes', 'array', 'min:1', 'max:'.self::MAX_COSTS],
            'costs.*.kind'                      => ['required', 'string'],
            'costs.*.id'                        => ['sometimes', 'integer'],
            'costs.*.amount'                    => ['sometimes', 'nullable', 'numeric', 'gte:0'],
            'costs.*.currency'                  => ['sometimes', 'string', 'size:3'],
            'costs.*.note'                      => ['sometimes', 'nullable', 'string', 'max:255'],
            'costs.*.not_applicable'            => ['sometimes', 'boolean'],
            'costs.*.waiting_for_invoice'       => ['sometimes', 'boolean'],
            'costs.*.invoice_expected_in_days'  => ['sometimes', 'integer', 'min:0', 'max:365'],
            'costs.*.remove'                    => ['sometimes', 'boolean'],
            'request_text'                      => ['required_with:costs', 'string', 'max:4000'],
            'allow_large_changes'               => ['sometimes', 'boolean'],
        ]);

        $user     = $request->user();
        $isSaving = $request->has('costs');

        if ($isSaving && !$user?->can_use_mcp_costing) {
            return Response::error('Saving stock delivery costs is not enabled for this user. Do not retry; an administrator enrols it on the user\'s edit page.');
        }

        $identifier   = strtolower((string) $request->string('organisation'));
        $organisation = Organisation::where('group_id', $user->group_id)
            ->where(fn ($query) => $query->whereRaw('lower(slug) = ?', [$identifier])->orWhereRaw('lower(code) = ?', [$identifier]))
            ->first();
        if (!$organisation || !$this->canAny($request, $organisation, [OrganisationPermissionsEnum::PROCUREMENT_VIEW, OrganisationPermissionsEnum::ACCOUNTING_VIEW])) {
            return $this->notFoundError('organisation', (string) $request->string('organisation'), $this->accessibleOrganisations($request), $request);
        }

        if ($isSaving && !$this->canAny($request, $organisation, [OrganisationPermissionsEnum::PROCUREMENT_EDIT, OrganisationPermissionsEnum::ACCOUNTING_EDIT])) {
            return Response::error("Stock delivery costs need procurement or accounting edit permission in {$organisation->code}, which this user does not have. Nothing was changed.");
        }

        $given         = strtolower((string) $request->string('stock_delivery'));
        $stockDelivery = StockDelivery::where('organisation_id', $organisation->id)
            ->where(fn ($query) => $query->whereRaw('lower(reference) = ?', [$given])->orWhereRaw('lower(slug) = ?', [$given]))
            ->first();
        if (!$stockDelivery) {
            return Response::error("'{$request->string('stock_delivery')}' is not a stock delivery of {$organisation->code}. Use its reference.");
        }

        if (!$isSaving) {
            return Response::json($this->show($stockDelivery, $extraKinds));
        }

        if ($stockDelivery->is_costed) {
            return Response::error("{$stockDelivery->reference} is already costed. An accounting manager changes a costed delivery with Update costing in Aiku. Nothing was changed.");
        }
        if (Arr::has($stockDelivery->data ?? [], 'costing_reopened') && !$stockDelivery->canUpdateCosting($request->user())) {
            return Response::error("The costing of {$stockDelivery->reference} is being updated by an accounting manager or the person they asked, who finishes it in Aiku. Nothing was changed.");
        }

        $plans = $this->plans($request, $stockDelivery, $organisation, $extraKinds);
        if (is_string($plans)) {
            return Response::error($plans.' Nothing was changed.');
        }

        if (!$request->boolean('allow_large_changes')) {
            $goodsValue = (float) DB::table('stock_delivery_items')->where('stock_delivery_id', $stockDelivery->id)->where('state', '!=', 'cancelled')->sum('org_net_amount');
            $localCosts = $this->localCostsInOrganisationCurrency($stockDelivery, $organisation, $plans);
            if ($goodsValue > 0 && $localCosts > $goodsValue) {
                return Response::error('Nothing was changed. These costs would add up to '.number_format($localCosts, 2, '.', '').' '.$organisation->currency->code.', more than the '.number_format($goodsValue, 2, '.', '').' '.$organisation->currency->code.' value of the goods, which is often an amount typed in the wrong currency. Show them to the user and, once confirmed, call again with allow_large_changes=true.');
            }
        }

        try {
            $this->recordChange(
                $request,
                McpChangeTypeEnum::STOCK_DELIVERY_COSTS,
                'Stock delivery costs of '.$stockDelivery->reference,
                ['stock_delivery_id' => $stockDelivery->id],
                fn () => DB::transaction(function () use ($plans, $stockDelivery) {
                    foreach ($plans as $plan) {
                        if ($plan['remove']) {
                            DeleteStockDeliveryCost::make()->action($plan['row']);
                        } elseif ($plan['row']) {
                            UpdateStockDeliveryCost::make()->action($plan['row'], $plan['data']);
                        } else {
                            StoreStockDeliveryCost::make()->action($stockDelivery, $plan['data']);
                        }
                    }
                }),
                ['organisation' => $organisation->slug, 'organisation_id' => $organisation->id]
            );
        } catch (ValidationException $exception) {
            return Response::error(implode(' ', $exception->validator->errors()->all()).' Check which costs were saved before trying again.');
        }

        $stockDelivery->refresh();

        return Response::json([
            'changed'       => (bool) $this->mcpChange,
            'change_log_id' => $this->mcpChange?->id,
            'stock_delivery' => $stockDelivery->reference,
            'costs'         => $this->rows($stockDelivery),
            'missing'       => $stockDelivery->costsMissing(),
            'is_costed'     => (bool) $stockDelivery->is_costed,
            'note'          => 'Entering the last cost completes the costing and values the stock put away from this delivery.',
        ]);
    }

    /**
     * @param array<int, OrganisationPermissionsEnum> $permissions
     */
    private function canAny(Request $request, Organisation $organisation, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->userCan($request, OrganisationPermissionsEnum::getPermissionName($permission->value, $organisation))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, string> $extraKinds
     * @return array<int, array{kind: string, row: ?StockDeliveryCost, remove: bool, data: array<string, mixed>}>|string
     */
    private function plans(Request $request, StockDelivery $stockDelivery, Organisation $organisation, array $extraKinds): array|string
    {
        $existing = $stockDelivery->costs()->get();
        $plans    = [];
        $seen     = [];

        foreach ($request->get('costs') as $entry) {
            $kind   = (string) $entry['kind'];
            $isFixed = in_array($kind, [StockDeliveryCostTypeEnum::SHIPPING->value, StockDeliveryCostTypeEnum::DUTY->value], true);

            if (!$isFixed && !in_array($kind, $extraKinds, true)) {
                return "'$kind' is not a cost kind. Use shipping, duty or one of: ".implode(', ', $extraKinds).'.';
            }

            $remove = (bool) ($entry['remove'] ?? false);
            $row    = null;

            if ($isFixed) {
                if (isset($entry['id']) || $remove) {
                    return "$kind is one row per delivery: it cannot be given an id or removed, only changed or marked not applicable.";
                }
                if (isset($seen[$kind])) {
                    return "$kind is given more than once; give it once.";
                }
                $seen[$kind] = true;
                $row         = $existing->first(fn (StockDeliveryCost $cost) => $cost->type->value === $kind);
            } elseif (isset($entry['id'])) {
                $row = $existing->first(fn (StockDeliveryCost $cost) => $cost->id === (int) $entry['id'] && $cost->type === StockDeliveryCostTypeEnum::EXTRA);
                if (!$row) {
                    return "Cost {$entry['id']} is not an extra expense of {$stockDelivery->reference}.";
                }
                if (isset($seen['id'.$row->id])) {
                    return "Cost {$row->id} is given more than once; give it once.";
                }
                $seen['id'.$row->id] = true;
            } elseif ($remove) {
                return 'Removing an extra expense needs its id.';
            }

            if ($row?->from_service_invoices) {
                return "The $kind cost {$row->id} comes from a service invoice, which is changed in Aiku, not here.";
            }

            $currency = null;
            if (isset($entry['currency'])) {
                $currency = Currency::whereRaw('upper(code) = ?', [strtoupper((string) $entry['currency'])])->first();
                if (!$currency) {
                    return "'{$entry['currency']}' is not a known currency code.";
                }
            }

            $plans[] = [
                'kind'   => $kind,
                'row'    => $row,
                'remove' => $remove,
                'data'   => $remove ? [] : $this->data($entry, $kind, $isFixed, $row, $currency ?? ($row ? null : $organisation->currency)),
            ];
        }

        return $plans;
    }

    /**
     * An amount typed means the cost arrived, so it stops waiting for its invoice unless the entry says it still does.
     *
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private function data(array $entry, string $kind, bool $isFixed, ?StockDeliveryCost $row, ?Currency $currency): array
    {
        $data = [];

        if (!$row) {
            $data['type'] = $isFixed ? $kind : StockDeliveryCostTypeEnum::EXTRA->value;
        }
        if (!$isFixed) {
            $data['category'] = $kind;
        }
        if (array_key_exists('amount', $entry)) {
            $data['amount'] = $entry['amount'];
            if ($entry['amount'] !== null && empty($entry['waiting_for_invoice'])) {
                $data['received_at']            = now();
                $data['is_waiting_for_invoice'] = false;
            }
        }
        if ($currency) {
            $data['currency_id'] = $currency->id;
        }
        if (array_key_exists('note', $entry)) {
            $data['label'] = $entry['note'];
        }
        if (array_key_exists('not_applicable', $entry)) {
            $data['is_na'] = (bool) $entry['not_applicable'];
        } elseif ($row?->is_na && ($entry['amount'] ?? null) !== null) {
            $data['is_na'] = false;
        }
        if (array_key_exists('waiting_for_invoice', $entry)) {
            $data['is_waiting_for_invoice'] = (bool) $entry['waiting_for_invoice'];
        }
        if (isset($entry['invoice_expected_in_days'])) {
            $data['invoice_expected_in_days'] = (int) $entry['invoice_expected_in_days'];
        }

        return $data;
    }

    /**
     * @param array<int, array{kind: string, row: ?StockDeliveryCost, remove: bool, data: array<string, mixed>}> $plans
     */
    private function localCostsInOrganisationCurrency(StockDelivery $stockDelivery, Organisation $organisation, array $plans): float
    {
        $planned = collect($plans)->filter(fn (array $plan) => $plan['row'])->keyBy(fn (array $plan) => $plan['row']->id);

        $rows = $stockDelivery->costs()->where('type', '!=', StockDeliveryCostTypeEnum::AGENT_INVOICE)->get()
            ->reject(fn (StockDeliveryCost $cost) => $planned->get($cost->id)['remove'] ?? false)
            ->map(function (StockDeliveryCost $cost) use ($planned) {
                $data = $planned->get($cost->id)['data'] ?? [];

                return [
                    'amount'      => array_key_exists('amount', $data) ? $data['amount'] : $cost->amount,
                    'currency_id' => array_key_exists('currency_id', $data) ? $data['currency_id'] : $cost->currency_id,
                    'is_na'       => $data['is_na'] ?? $cost->is_na,
                ];
            })
            ->concat(collect($plans)->filter(fn (array $plan) => !$plan['row'])->map(fn (array $plan) => [
                'amount'      => $plan['data']['amount'] ?? null,
                'currency_id' => $plan['data']['currency_id'] ?? null,
                'is_na'       => $plan['data']['is_na'] ?? false,
            ]));

        return (float) $rows
            ->reject(fn (array $row) => $row['is_na'] || $row['amount'] === null)
            ->sum(function (array $row) use ($stockDelivery, $organisation) {
                $amount = (float) $row['amount'];

                if ($row['currency_id'] == $organisation->currency_id) {
                    return $amount;
                }
                if (!$row['currency_id'] || $row['currency_id'] == $stockDelivery->currency_id) {
                    return $amount * (float) ($stockDelivery->org_exchange ?: 1);
                }

                return $amount * (float) GetCurrencyExchange::run(Currency::findOrFail($row['currency_id']), $organisation->currency);
            });
    }

    /**
     * @param array<int, string> $extraKinds
     * @return array<string, mixed>
     */
    private function show(StockDelivery $stockDelivery, array $extraKinds): array
    {
        $organisationCurrency = $stockDelivery->organisation->currency->code;

        return [
            'stock_delivery'        => $stockDelivery->reference,
            'state'                 => $stockDelivery->state->value,
            'delivery_currency'     => $stockDelivery->currency->code,
            'organisation_currency' => $organisationCurrency,
            'is_costed'             => (bool) $stockDelivery->is_costed,
            'goods_value'           => round((float) DB::table('stock_delivery_items')->where('stock_delivery_id', $stockDelivery->id)->sum('org_net_amount'), 2),
            'goods_value_currency'  => $organisationCurrency,
            'costs'                 => $this->rows($stockDelivery),
            'missing'               => $stockDelivery->costsMissing(),
            'extra_kinds'           => $extraKinds,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rows(StockDelivery $stockDelivery): array
    {
        return $stockDelivery->costs()->with('currency')->orderBy('id')->get()
            ->map(fn (StockDeliveryCost $cost) => [
                'id'                    => $cost->id,
                'kind'                  => $cost->type === StockDeliveryCostTypeEnum::EXTRA ? $cost->category?->value : $cost->type->value,
                'note'                  => $cost->label,
                'amount'                => $cost->amount !== null ? (float) $cost->amount : null,
                'currency'              => $cost->currency?->code ?? $stockDelivery->currency->code,
                'not_applicable'        => $cost->is_na,
                'waiting_for_invoice'   => $cost->is_waiting_for_invoice,
                'is_estimate'           => $cost->is_estimate,
                'waiting_label'         => $cost->waitingLabel(),
                'from_service_invoices' => $cost->from_service_invoices,
                'tariff_codes'          => $cost->applies_to_tariff_codes,
            ])
            ->all();
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'organisation'        => $schema->string()->description('Organisation slug or code')->required(),
            'stock_delivery'      => $schema->string()->description('Stock delivery reference or slug')->required(),
            'costs'               => $schema->array()->items($schema->object())->description('Costs to save, up to 30: [{"kind": "shipping", "amount": 120, "currency": "EUR", "note": "Sea freight"}, {"kind": "duty", "not_applicable": true}, {"kind": "fine", "amount": 30, "note": "Late pickup"}]. kind is shipping, duty or an extra kind (the show lists them). id changes an existing extra expense (without it an extra kind adds one); amount is a number or null; currency is an ISO code, default the organisation currency; note up to 255 characters; not_applicable and waiting_for_invoice are booleans (waiting with an amount is an estimate); invoice_expected_in_days is 0-365; remove=true deletes an extra expense and needs its id. Omit to only show'),
            'request_text'        => $schema->string()->description('The user\'s request, verbatim; required when saving'),
            'allow_large_changes' => $schema->boolean()->description('True only after the user confirmed local costs above the value of the goods that a first call refused'),
        ];
    }
}
