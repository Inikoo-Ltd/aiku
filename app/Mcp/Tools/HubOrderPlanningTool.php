<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 02 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\Procurement\OrgPartner\GetPartnerLeadTime;
use App\Actions\Procurement\OrgPartner\GetPartnerOrderCapacity;
use App\Actions\Procurement\PartnerShoppingListItem\SuggestPartnerShoppingList;
use App\Enums\SysAdmin\Authorisation\OrganisationPermissionsEnum;
use App\Models\Procurement\OrgPartner;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

/**
 * The numbers a buyer needs to order from the manufacturing hub, from the same query the
 * shopping list auto-fill uses. Hub products without a recorded shelf life are planned as
 * lasting one year, so an assistant never orders more than sells before it expires.
 */
#[Description('Planning data to order from the manufacturing hub, one row per SKO the hub sells, most urgent first (fewest days until we run out). Quantities are SKOs. Per SKO: our stock, our sales per quarter and per day, days until out of stock (expected and pessimistic) and the predicted date, the recommended order quantity from our forecast, what the hub has ready now (0 means it has to be made first), lead time, how many of our purchase orders already bring it, the quantity already on our open list, the order step (production makes whole batches: order in multiples of order_step_skos so it is made sooner; an order below one step waits until other demand joins it), shelf life and max_order_before_expiry (never order more than that: it would expire before we sell it; shelf life not recorded yet is assumed to be one year), price per SKO in our currency, ABC rank by revenue (A best), and do_not_auto_order (only order those when the user names them). suggested_quantity already applies forecast, order step and expiry cap. The header has the budget: a list worth more than budget_per_order_cycle is refused except A-rank and out-of-stock SKOs. Use hub-shopping-list-tool to put the agreed lines on the list.')]
class HubOrderPlanningTool extends Tool
{
    use WithMcpPermissions;

    public const int ASSUMED_SHELF_LIFE_DAYS = 365;

    public function handle(Request $request): Response
    {
        $request->validate([
            'organisation' => ['required', 'string'],
            'codes'        => ['sometimes', 'array', 'max:300'],
            'codes.*'      => ['string'],
            'search'       => ['sometimes', 'string', 'max:100'],
            'limit'        => ['sometimes', 'integer', 'min:1', 'max:300'],
            'offset'       => ['sometimes', 'integer', 'min:0'],
        ]);

        $identifier   = strtolower((string) $request->string('organisation'));
        $organisation = Organisation::whereRaw('lower(slug) = ?', [$identifier])->orWhereRaw('lower(code) = ?', [$identifier])->first();
        if (!$organisation || !$this->userCan($request, OrganisationPermissionsEnum::getPermissionName(OrganisationPermissionsEnum::PROCUREMENT_VIEW->value, $organisation))) {
            return $this->notFoundError('organisation', (string) $request->string('organisation'), $this->accessibleOrganisations($request), $request);
        }

        $orgPartner = OrgPartner::where('organisation_id', $organisation->id)
            ->where('status', true)
            ->whereHas('partner', fn ($query) => $query->where('is_manufacturing_hub', true))
            ->with('partner')
            ->first();
        if (!$orgPartner) {
            return Response::error("{$organisation->code} does not buy from a manufacturing hub.");
        }

        $suggest  = SuggestPartnerShoppingList::make();
        $leadTime = GetPartnerLeadTime::run($orgPartner);
        $capacity = GetPartnerOrderCapacity::run($orgPartner);

        $query = $suggest->candidatesQuery($orgPartner)
            ->addSelect([
                'partner_shopping_list_items.quantity as on_list_quantity',
                'buyer_stats.days_of_cover_pessimistic as buyer_days_of_cover_pessimistic',
                'buyer_stats.predicted_out_of_stock_at as buyer_predicted_out_of_stock_at',
                'buyer_stats.on_the_way_po_count as buyer_on_the_way_po_count',
                'buyer_org_stocks.is_excluded_from_auto_ordering as buyer_do_not_auto_order',
                DB::raw('coalesce(buyer_org_stocks.measured_lead_time_days, buyer_org_stocks.estimated_lead_time_days) as buyer_lead_time_days'),
                DB::raw('(select shelf_life_days from artefacts where artefacts.org_stock_id = org_stocks.id and artefacts.deleted_at is null and artefacts.shelf_life_days is not null limit 1) as shelf_life_days'),
            ]);

        if ($request->has('codes')) {
            $query->whereIn(DB::raw('lower(org_stocks.code)'), array_map(fn ($code) => strtolower(trim($code)), $request->get('codes')));
        }
        if ($request->filled('search')) {
            $search = '%'.strtolower((string) $request->string('search')).'%';
            $query->where(fn ($query) => $query->whereRaw('lower(org_stocks.code) like ?', [$search])->orWhereRaw('lower(org_stocks.name) like ?', [$search]));
        }

        $total = (clone $query)->distinct()->count('org_stocks.id');
        $rows  = $query
            ->orderByRaw('buyer_stats.days_of_cover asc nulls last')
            ->orderBy('org_stocks.code')
            ->offset((int) $request->get('offset', 0))
            ->limit((int) $request->get('limit', 100))
            ->get()
            ->unique('id');

        $exchange = $suggest->exchange($orgPartner);

        return Response::json([
            'hub'                     => $orgPartner->partner->code.' ('.$orgPartner->partner->name.')',
            'currency'                => $organisation->currency->code,
            'lead_time_days'          => $leadTime['days'].' ('.$leadTime['source'].')',
            'budget_per_order_cycle'  => $capacity['partner_capacity']['delivers_to_us_per_30d'],
            'open_list_value'         => $capacity['list']['value'],
            'warehouse_full'          => $capacity['blocked']['warehouse_full'],
            'total_skos'              => $total,
            'rows'                    => $rows->map(fn ($row) => $this->row($suggest->candidate($row, $exchange), $row, $leadTime['days']))->values()->all(),
        ]);
    }

    /**
     * @param array<string, mixed> $candidate
     *
     * @return array<string, mixed>
     */
    private function row(array $candidate, object $row, int $hubLeadTimeDays): array
    {
        $dailyUsage    = (float) ($row->buyer_daily_usage ?? 0);
        $shelfLifeDays = $row->shelf_life_days !== null ? (int) $row->shelf_life_days : self::ASSUMED_SHELF_LIFE_DAYS;
        $orderStep     = $candidate['order_quantum'];

        $maxBeforeExpiry = $dailyUsage > 0 ? (int) max(0, floor($dailyUsage * $shelfLifeDays - $candidate['buyer_available'])) : null;

        return [
            'sko'                         => $candidate['code'],
            'name'                        => $candidate['name'],
            'our_stock'                   => $candidate['buyer_available'],
            'our_sales_per_quarter'       => $candidate['quarterly_usage'],
            'our_sales_per_day'           => round($dailyUsage, 2),
            'days_until_out_of_stock'     => $candidate['days_of_cover'] !== null ? round($candidate['days_of_cover']) : null,
            'days_until_out_pessimistic'  => $row->buyer_days_of_cover_pessimistic !== null ? round((float) $row->buyer_days_of_cover_pessimistic) : null,
            'predicted_out_of_stock_on'   => $row->buyer_predicted_out_of_stock_at ? substr($row->buyer_predicted_out_of_stock_at, 0, 10) : null,
            'recommended_order_quantity'  => $candidate['recommended'],
            'hub_ready_now'               => $candidate['partner_available'],
            'lead_time_days'              => $row->buyer_lead_time_days !== null ? (int) $row->buyer_lead_time_days : $hubLeadTimeDays,
            'our_purchase_orders_on_way'  => (int) ($row->buyer_on_the_way_po_count ?? 0),
            'on_our_open_list'            => $row->on_list_quantity !== null ? (float) $row->on_list_quantity : 0,
            'order_step_skos'             => $orderStep,
            'shelf_life_days'             => $shelfLifeDays,
            'shelf_life_recorded'         => $row->shelf_life_days !== null,
            'max_order_before_expiry'     => $maxBeforeExpiry,
            'suggested_quantity'          => $this->suggestedQuantity($candidate['recommended'], $orderStep, $maxBeforeExpiry),
            'price_per_sko'               => $candidate['price_per_sko'],
            'abc_rank'                    => $candidate['health_rank'],
            'never_stocked_by_us'         => $candidate['never_stocked'],
            'do_not_auto_order'           => (bool) $row->buyer_do_not_auto_order,
        ];
    }

    private function suggestedQuantity(?float $recommended, int $orderStep, ?int $maxBeforeExpiry): int
    {
        if (!$recommended || $recommended <= 0) {
            return 0;
        }

        $quantity = ceil($recommended / $orderStep) * $orderStep;

        if ($maxBeforeExpiry !== null && $quantity > $maxBeforeExpiry) {
            $quantity = floor($maxBeforeExpiry / $orderStep) * $orderStep ?: $maxBeforeExpiry;
        }

        return (int) $quantity;
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'organisation' => $schema->string()->description('Slug or code of the buying organisation')->required(),
            'codes'        => $schema->array()->items($schema->string())->description('Only these SKO codes'),
            'search'       => $schema->string()->description('Part of a SKO code or name'),
            'limit'        => $schema->integer()->description('Rows to return, default 100, max 300'),
            'offset'       => $schema->integer()->description('Rows to skip, to page through total_skos'),
        ];
    }
}
