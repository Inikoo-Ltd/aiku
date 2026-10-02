<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 02 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\Procurement\PartnerShoppingListItem\StorePartnerShoppingListItems;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Enums\SysAdmin\Authorisation\OrganisationPermissionsEnum;
use App\Enums\SysAdmin\McpChange\McpChangeTypeEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

/**
 * Procurement write tools are gated by can_use_mcp_procurement on the user's account, on top of
 * the procurement edit permission the same change needs in the UI. Lines go through the same
 * action as the shopping list page, so warehouse space and packed-in guards still apply.
 */
#[Description('Shows or fills the shopping list an organisation sends to the manufacturing hub. Without lines it only shows the open list. With lines it sets the quantity (in SKOs) of each SKO code on the list: a SKO already on the list gets the new quantity, not an extra one. Lines the hub cannot take are reported as skipped with the reason. Only write after the user confirmed the codes and quantities in their own words, passing their request text. Only for users enrolled to fill the hub shopping list through their assistant.')]
class HubShoppingListTool extends Tool
{
    use WithMcpPermissions;
    use WithMcpChangeLog;

    public function handle(Request $request): Response
    {
        $request->validate([
            'organisation'     => ['required', 'string'],
            'lines'            => ['sometimes', 'array', 'min:1', 'max:200'],
            'lines.*.sko'      => ['required', 'string'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'lines.*.notes'    => ['sometimes', 'nullable', 'string', 'max:500'],
            'request_text'     => ['required_with:lines', 'string', 'max:4000'],
        ]);

        if (!$request->user()?->can_use_mcp_procurement) {
            return Response::error('Filling the hub shopping list is not enabled for this user. Do not retry; an administrator enrols it on the user\'s edit page.');
        }

        $identifier   = strtolower((string) $request->string('organisation'));
        $organisation = Organisation::whereRaw('lower(slug) = ?', [$identifier])->orWhereRaw('lower(code) = ?', [$identifier])->first();
        if (!$organisation || !$this->userCan($request, OrganisationPermissionsEnum::getPermissionName(OrganisationPermissionsEnum::PROCUREMENT_VIEW->value, $organisation))) {
            return $this->notFoundError('organisation', (string) $request->string('organisation'), $this->accessibleOrganisations($request), $request);
        }

        $orgPartner = OrgPartner::where('organisation_id', $organisation->id)
            ->where('status', true)
            ->whereHas('partner', fn ($query) => $query->where('is_manufacturing_hub', true))
            ->with('partner:id,code,name')
            ->first();
        if (!$orgPartner) {
            return Response::error("{$organisation->code} does not buy from a manufacturing hub.");
        }

        if (!$request->has('lines')) {
            return Response::json($this->summary($orgPartner));
        }

        if (!$this->userCan($request, OrganisationPermissionsEnum::getPermissionName(OrganisationPermissionsEnum::PROCUREMENT_EDIT->value, $organisation))) {
            return Response::error("This user cannot edit procurement in {$organisation->code}. Nothing was changed.");
        }

        $lines    = collect($request->get('lines'))->keyBy(fn (array $line) => strtolower(trim($line['sko'])));
        $orgStocks = OrgStock::whereIn('organisation_id', [$organisation->id, $orgPartner->partner_id])
            ->whereIn(DB::raw('lower(code)'), $lines->keys()->all())
            ->orderByRaw('organisation_id = ? desc', [$organisation->id])
            ->get()
            ->unique(fn (OrgStock $orgStock) => strtolower($orgStock->code))
            ->keyBy(fn (OrgStock $orgStock) => strtolower($orgStock->code));

        $missing = $lines->diffKeys($orgStocks)->pluck('sko');
        if ($missing->isNotEmpty()) {
            return Response::error('Unknown SKO codes in '.$organisation->code.' or '.$orgPartner->partner->code.': '.$missing->implode(', ').'. Nothing was changed.');
        }

        $result = $this->recordChange(
            $request,
            McpChangeTypeEnum::PARTNER_SHOPPING_LIST,
            "Shopping list of {$organisation->code} to {$orgPartner->partner->code}: ".$orgStocks->pluck('code')->implode(', '),
            ['org_partner_id' => $orgPartner->id, 'stock_ids' => $orgStocks->pluck('stock_id')->unique()->values()->all()],
            fn () => StorePartnerShoppingListItems::make()->action($orgPartner, $lines->map(fn (array $line, string $code) => [
                'org_stock_id' => $orgStocks[$code]->id,
                'quantity'     => $line['quantity'],
                'notes'        => $line['notes'] ?? null,
            ])->values()->all()),
            ['organisation' => $organisation->code]
        );

        $codesById = $orgStocks->pluck('code', 'id');

        return Response::json([
            'changed'       => $result['created'] > 0,
            'change_log_id' => $this->mcpChange?->id,
            'set'           => $result['created'],
            'skipped'       => collect($result['skipped'])->map(fn (array $skipped) => [
                'sko'    => $codesById[$skipped['org_stock_id']] ?? $skipped['org_stock_id'],
                'reason' => $skipped['reason'],
            ])->all(),
            'over_budget'   => $result['over_budget'],
            ...$this->summary($orgPartner),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(OrgPartner $orgPartner): array
    {
        return [
            'hub'        => $orgPartner->partner->code.' ('.$orgPartner->partner->name.')',
            'open_lines' => PartnerShoppingListItem::where('org_partner_id', $orgPartner->id)
                ->where('state', ShoppingListItemStateEnum::OPEN)
                ->with('orgStock:id,code,name')
                ->orderByDesc('id')
                ->limit(300)
                ->get()
                ->map(fn (PartnerShoppingListItem $item) => [
                    'sko'           => $item->orgStock->code,
                    'name'          => $item->orgStock->name,
                    'quantity'      => (float) $item->quantity,
                    'priority'      => $item->priority?->value,
                    'notes'         => $item->notes,
                    'being_prepared' => $item->pre_picked_at || $item->job_order_id,
                ])
                ->all(),
        ];
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'organisation' => $schema->string()->description('Slug or code of the buying organisation')->required(),
            'lines'        => $schema->array()->items($schema->object([
                'sko'      => $schema->string()->description('SKO code')->required(),
                'quantity' => $schema->number()->description('SKOs wanted on the list for this code')->required(),
                'notes'    => $schema->string()->description('Optional note for the hub'),
            ]))->description('SKOs to put on the list, up to 200. Omit to only show the open list'),
            'request_text' => $schema->string()->description('The user\'s request, verbatim; required when writing'),
        ];
    }
}
