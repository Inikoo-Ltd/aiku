<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\Procurement\OrgPartner\GetPartnerStockCoverBuckets;
use App\Actions\Procurement\OrgPartner\StoreRescuePurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrderStateToSubmitted;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\SysAdmin\Authorisation\OrganisationPermissionsEnum;
use App\Enums\SysAdmin\McpChange\McpChangeTypeEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

/**
 * The rescue purchase order of the partner page, placed by an assistant: the same action picks the
 * lines, and the same submit sends it to the partner's warehouse. Placing needs
 * can_use_mcp_place_orders.
 */
#[Description('Rescue purchase orders to partners (sister companies that are not the manufacturing hub; order from the hub with hub-shopping-list-tool). Without place it previews, per partner of the organisation, the SKOs it can rescue that would lose sales: code, SKOs, cost in our currency and the total, and the order already being prepared. With place=true and a partner, for users enrolled to place orders, it puts those lines on the order being prepared (or a new one) within the optional budget and submits it: the partner\'s warehouse starts picking at once and it cannot be undone from the AI changes log. Only place after showing the preview and the user confirmed the partner, lines and budget in their own words, passing their request text.')]
class PartnerRescueOrderTool extends Tool
{
    use WithMcpPermissions;
    use WithMcpChangeLog;

    public function handle(Request $request): Response
    {
        $request->validate([
            'organisation' => ['required', 'string'],
            'partner'      => ['required_if:place,true', 'string'],
            'budget'       => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'buckets'      => ['sometimes', 'array', 'min:1'],
            'buckets.*'    => ['string', Rule::in(GetPartnerStockCoverBuckets::DEFAULT_ORDER_BUCKETS)],
            'place'        => ['sometimes', 'boolean'],
            'request_text' => ['required_if:place,true', 'string', 'max:4000'],
        ]);

        $identifier   = strtolower((string) $request->string('organisation'));
        $organisation = Organisation::whereRaw('lower(slug) = ?', [$identifier])->orWhereRaw('lower(code) = ?', [$identifier])->first();
        if (!$organisation || !$this->userCan($request, OrganisationPermissionsEnum::getPermissionName(OrganisationPermissionsEnum::PROCUREMENT_VIEW->value, $organisation))) {
            return $this->notFoundError('organisation', (string) $request->string('organisation'), $this->accessibleOrganisations($request), $request);
        }

        $partnerCode = strtolower((string) $request->string('partner'));
        $orgPartners = OrgPartner::where('organisation_id', $organisation->id)
            ->where('status', true)
            ->whereHas('partner', fn ($query) => $query->where('is_manufacturing_hub', false))
            ->when($partnerCode, fn ($query) => $query->whereHas('partner', fn ($query) => $query->whereRaw('(lower(code) = ? or lower(slug) = ?)', [$partnerCode, $partnerCode])))
            ->with('partner')
            ->get();
        if ($orgPartners->isEmpty()) {
            return Response::error($partnerCode ? "{$organisation->code} has no partner {$request->string('partner')} it can rescue from." : "{$organisation->code} has no partners to rescue from.");
        }

        $buckets = $request->get('buckets', GetPartnerStockCoverBuckets::DEFAULT_ORDER_BUCKETS);

        if (!$request->boolean('place')) {
            return Response::json($orgPartners->map(fn (OrgPartner $orgPartner) => $this->preview($orgPartner, $buckets))->values()->all());
        }

        if (!$this->userCan($request, OrganisationPermissionsEnum::getPermissionName(OrganisationPermissionsEnum::PROCUREMENT_EDIT->value, $organisation))) {
            return Response::error("This user cannot edit procurement in {$organisation->code}. Nothing was changed.");
        }

        if ($refusal = $this->orderPlacingRefusal($request)) {
            return Response::error($refusal);
        }

        $orgPartner = $orgPartners->first();
        $budget     = $request->get('budget');

        try {
            [$purchaseOrder, $submitError] = $this->recordChange(
                $request,
                McpChangeTypeEnum::PLACED_ORDER,
                "Rescue order of {$organisation->code} to {$orgPartner->partner->code}",
                ['org_partner_id' => $orgPartner->id],
                fn () => $this->place($orgPartner, $budget === null ? null : (float) $budget, $buckets),
                ['organisation' => $organisation->code]
            );
        } catch (ValidationException $exception) {
            return Response::error(collect($exception->errors())->flatten()->implode(' ').' Nothing was sent.');
        }

        return Response::json([
            'changed'        => true,
            'order_log_id'   => $this->mcpChange?->id,
            'purchase_order' => $purchaseOrder->reference,
            'state'          => $purchaseOrder->state->value,
            'lines'          => $purchaseOrder->purchaseOrderTransactions()->count(),
            'total'          => $purchaseOrder->organisation->currency->code.' '.number_format((float) $purchaseOrder->purchaseOrderTransactions()->sum('org_net_amount'), 2),
            'submit_error'   => $submitError,
        ]);
    }

    /**
     * The order stays in process with its lines when the partner cannot take it yet (no shop set
     * up, a SKO it does not sell), so a person can fix it on the purchase order page.
     *
     * @param  array<int, string>  $buckets
     *
     * @return array{0: PurchaseOrder, 1: ?string}
     */
    private function place(OrgPartner $orgPartner, ?float $budget, array $buckets): array
    {
        $purchaseOrder = StoreRescuePurchaseOrder::make()->handle($orgPartner, $budget, $buckets);

        try {
            return [UpdatePurchaseOrderStateToSubmitted::make()->action($purchaseOrder->refresh()), null];
        } catch (ValidationException $exception) {
            return [$purchaseOrder->refresh(), collect($exception->errors())->flatten()->implode(' ').' The order was left in process for a person to fix and submit.'];
        }
    }

    /**
     * @param  array<int, string>  $buckets
     *
     * @return array<string, mixed>
     */
    private function preview(OrgPartner $orgPartner, array $buckets): array
    {
        $lines         = collect(GetPartnerStockCoverBuckets::make()->rescueLines($orgPartner, $buckets));
        $codes         = OrgStock::whereIn('id', $lines->pluck('org_stock_id'))->pluck('code', 'id');
        $beingPrepared = $orgPartner->purchaseOrders()->where('state', PurchaseOrderStateEnum::IN_PROCESS)->latest()->first();

        return [
            'partner'          => $orgPartner->partner->code.' ('.$orgPartner->partner->name.')',
            'being_prepared'   => $beingPrepared ? [
                'reference' => $beingPrepared->reference,
                'note'      => 'Placing adds the rescue lines to this order and sends it whole, including the lines already on it',
                'lines'     => $beingPrepared->purchaseOrderTransactions()->count(),
                'total'     => round((float) $beingPrepared->purchaseOrderTransactions()->sum('org_net_amount'), 2),
            ] : null,
            'currency'         => $orgPartner->organisation->currency->code,
            'total'            => round($lines->sum('cost'), 2),
            'lines'            => $lines->map(fn (array $line) => [
                'sko'  => $codes[$line['org_stock_id']] ?? $line['org_stock_id'],
                'skos' => $line['skos'],
                'cost' => $line['cost'],
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'organisation' => $schema->string()->description('Slug or code of the buying organisation')->required(),
            'partner'      => $schema->string()->description('Code or slug of the partner organisation; required to place'),
            'budget'       => $schema->number()->description('Most to spend on the lines added this time, in our currency; lines over it are skipped'),
            'buckets'      => $schema->array()->items($schema->string())->description('Which shortages to rescue: out (out of stock), w1, w2 (out within one or two lead times). Default all three'),
            'place'        => $schema->boolean()->description('Users enrolled to place orders only: build and submit the rescue order to the partner'),
            'request_text' => $schema->string()->description('The user\'s request, verbatim; required to place'),
        ];
    }
}
