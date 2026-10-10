<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 02 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\Procurement\PartnerShoppingListItem\StorePartnerShoppingListItems;
use App\Actions\Procurement\PartnerShoppingListItem\SubmitPartnerShoppingList;
use App\Actions\Procurement\PartnerShoppingListItem\UpdatePartnerShoppingListItem;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Enums\SysAdmin\Authorisation\OrganisationPermissionsEnum;
use App\Enums\SysAdmin\McpChange\McpChangeTypeEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

/**
 * Procurement write tools are gated by can_use_mcp_procurement on the user's account, on top of
 * the procurement edit permission the same change needs in the UI. Lines go through the same
 * action as the shopping list page, so warehouse space and packed-in guards still apply.
 * Submitting the basket and changing lines already sent are orders: they also need
 * can_use_mcp_place_orders.
 */
#[Description('Shows, fills and submits the shopping list (the basket) an organisation sends to the manufacturing hub. Plan the quantities with hub-order-planning-tool first. Without lines, sent_lines or submit it only shows the list (draft and sent lines). With lines it sets the quantity (in SKOs) of each SKO code as a draft in the basket: a SKO already in the basket gets the new quantity, not an extra one. The hub does not see drafts until the basket is submitted. The hub makes whole production batches only, so each quantity is raised to the next multiple of whole batches (open_lines shows what was saved); breaking a batch is only possible by a person on the shopping list page. Lines the hub cannot take are reported as skipped with the reason. For users enrolled to place orders: submit=true sends every draft to the hub, which starts producing it; sent_lines changes the quantity of lines already sent that the hub has not started (can_still_change in open_lines). Changing a sent line is dangerous: the hub may already be planning materials and batches around it. The first call is refused with that warning; tell the user, and only after they insist call again with accept ["change_submitted"]. Orders cannot be undone from the AI changes log. Only write after the user confirmed the codes, quantities and the submit in their own words, passing their request text.')]
class HubShoppingListTool extends Tool
{
    use WithMcpPermissions;
    use WithMcpChangeLog;

    public function handle(Request $request): Response
    {
        $request->validate([
            'organisation'          => ['required', 'string'],
            'lines'                 => ['sometimes', 'array', 'min:1', 'max:200'],
            'lines.*.sko'           => ['required', 'string'],
            'lines.*.quantity'      => ['required', 'numeric', 'min:0.01'],
            'lines.*.notes'         => ['sometimes', 'nullable', 'string', 'max:500'],
            'sent_lines'            => ['sometimes', 'array', 'min:1', 'max:200'],
            'sent_lines.*.sko'      => ['required', 'string'],
            'sent_lines.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'submit'                => ['sometimes', 'boolean'],
            'accept'                => ['sometimes', 'array'],
            'accept.*'              => ['string'],
            'request_text'          => ['required_with:lines,sent_lines', 'required_if_accepted:submit', 'string', 'max:4000'],
        ]);

        $placesOrder = $request->boolean('submit') || $request->has('sent_lines');

        if (!$request->user()?->can_use_mcp_procurement && !$request->user()?->can_use_mcp_place_orders) {
            return Response::error('The hub shopping list is not enabled for this user. Do not retry; an administrator enrols it on the user\'s edit page.');
        }

        if ($request->has('lines') && !$request->user()?->can_use_mcp_procurement) {
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
            ->with('partner')
            ->first();
        if (!$orgPartner) {
            return Response::error("{$organisation->code} does not buy from a manufacturing hub.");
        }

        if (!$request->has('lines') && !$placesOrder) {
            return Response::json($this->summary($orgPartner));
        }

        if (!$this->userCan($request, OrganisationPermissionsEnum::getPermissionName(OrganisationPermissionsEnum::PROCUREMENT_EDIT->value, $organisation))) {
            return Response::error("This user cannot edit procurement in {$organisation->code}. Nothing was changed.");
        }

        if ($placesOrder && $refusal = $this->orderPlacingRefusal($request)) {
            return Response::error($refusal);
        }

        if ($request->has('sent_lines') && !in_array('change_submitted', $request->get('accept', []), true)) {
            return Response::error('Dangerous: these lines were already sent to '.$orgPartner->partner->name.', which may already be planning materials and production batches around them. Tell the user, and only if they still want it call again with accept ["change_submitted"]. Nothing was changed.');
        }

        $lines     = collect($request->get('lines', []))->keyBy(fn (array $line) => strtolower(trim($line['sko'])));
        $sentLines = collect($request->get('sent_lines', []))->keyBy(fn (array $line) => strtolower(trim($line['sko'])));
        $orgStocks = $this->orgStocks($organisation, $orgPartner, $lines->keys()->merge($sentLines->keys())->unique());

        $missing = $lines->merge($sentLines)->diffKeys($orgStocks)->pluck('sko');
        if ($missing->isNotEmpty()) {
            return Response::error('Unknown SKO codes in '.$organisation->code.' or '.$orgPartner->partner->code.': '.$missing->implode(', ').'. Nothing was changed.');
        }

        $codesById = $orgStocks->pluck('code', 'id');
        $response  = [];

        if ($lines->isNotEmpty()) {
            $result = $this->recordChange(
                $request,
                McpChangeTypeEnum::PARTNER_SHOPPING_LIST,
                "Shopping list of {$organisation->code} to {$orgPartner->partner->code}: ".$lines->keys()->map(fn (string $code) => $orgStocks[$code]->code)->implode(', '),
                ['org_partner_id' => $orgPartner->id, 'stock_ids' => $lines->keys()->map(fn (string $code) => $orgStocks[$code]->stock_id)->unique()->values()->all()],
                fn () => StorePartnerShoppingListItems::make()->action($orgPartner, $lines->map(fn (array $line, string $code) => [
                    'org_stock_id' => $orgStocks[$code]->id,
                    'quantity'     => $line['quantity'],
                    'notes'        => $line['notes'] ?? null,
                ])->values()->all()),
                ['organisation' => $organisation->code]
            );

            $response = [
                'changed'       => $result['created'] > 0,
                'change_log_id' => $this->mcpChange?->id,
                'set'           => $result['created'],
                'skipped'       => collect($result['skipped'])->map(fn (array $skipped) => [
                    'sko'    => $codesById[$skipped['org_stock_id']] ?? $skipped['org_stock_id'],
                    'reason' => $skipped['reason'],
                ])->all(),
                'over_budget'   => $result['over_budget'],
            ];
        }

        if ($placesOrder) {
            $this->mcpChange = null;
            $placed = $this->recordChange(
                $request,
                McpChangeTypeEnum::PLACED_ORDER,
                ($request->boolean('submit') ? "Basket of {$organisation->code} submitted to {$orgPartner->partner->code}" : "Sent lines of {$organisation->code} to {$orgPartner->partner->code} changed")
                    .($sentLines->isNotEmpty() ? ': '.$sentLines->keys()->map(fn (string $code) => $orgStocks[$code]->code)->implode(', ') : ''),
                ['org_partner_id' => $orgPartner->id, 'stock_ids' => $sentLines->keys()->map(fn (string $code) => $orgStocks[$code]->stock_id)->unique()->values()->all()],
                fn () => $this->placeOrder($orgPartner, $sentLines, $orgStocks, $request->boolean('submit')),
                ['organisation' => $organisation->code]
            );

            $response = [
                ...$response,
                'changed'           => ($response['changed'] ?? false) || $placed['submitted'] > 0 || $placed['sent_lines_changed'] > 0,
                'order_log_id'      => $this->mcpChange?->id,
                'submitted'         => $placed['submitted'],
                'sent_lines_changed' => $placed['sent_lines_changed'],
                'sent_lines_skipped' => $placed['sent_lines_skipped'],
                'submit_error'      => $placed['submit_error'],
            ];
        }

        return Response::json([...$response, ...$this->summary($orgPartner)]);
    }

    /**
     * @param  Collection<string, OrgStock>  $orgStocks
     * @param  Collection<string, array{sko: string, quantity: float}>  $sentLines
     *
     * @return array{submitted: int, sent_lines_changed: int, sent_lines_skipped: array<int, array{sko: string, reason: string}>, submit_error: ?string}
     */
    private function placeOrder(OrgPartner $orgPartner, Collection $sentLines, Collection $orgStocks, bool $submit): array
    {
        $changed = 0;
        $skipped = [];

        foreach ($sentLines as $code => $line) {
            $reason = DB::transaction(function () use ($orgPartner, $orgStocks, $code, $line) {
                $item = PartnerShoppingListItem::where('org_partner_id', $orgPartner->id)
                    ->where('org_stock_id', $orgStocks[$code]->id)
                    ->where('state', ShoppingListItemStateEnum::OPEN)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->first(fn (PartnerShoppingListItem $item) => $item->isWaitingForPartner());

                if (!$item) {
                    return 'No sent line the hub has not started yet';
                }

                try {
                    UpdatePartnerShoppingListItem::make()->action($item, ['quantity' => $line['quantity']]);
                } catch (HttpException $exception) {
                    return $exception->getMessage();
                }

                return null;
            });

            if ($reason) {
                $skipped[] = ['sko' => $orgStocks[$code]->code, 'reason' => $reason];
                continue;
            }
            $changed++;
        }

        $submitted   = 0;
        $submitError = null;
        if ($submit) {
            try {
                $submitted = SubmitPartnerShoppingList::make()->action($orgPartner);
            } catch (ValidationException $exception) {
                $submitError = collect($exception->errors())->flatten()->first();
            }
        }

        return ['submitted' => $submitted, 'sent_lines_changed' => $changed, 'sent_lines_skipped' => $skipped, 'submit_error' => $submitError];
    }

    /**
     * @param  Collection<int, string>  $codes
     *
     * @return Collection<string, OrgStock>
     */
    private function orgStocks(Organisation $organisation, OrgPartner $orgPartner, Collection $codes): Collection
    {
        return OrgStock::whereIn('organisation_id', [$organisation->id, $orgPartner->partner_id])
            ->whereIn(DB::raw('lower(code)'), $codes->all())
            ->orderByRaw('organisation_id = ? desc', [$organisation->id])
            ->get()
            ->unique(fn (OrgStock $orgStock) => strtolower($orgStock->code))
            ->keyBy(fn (OrgStock $orgStock) => strtolower($orgStock->code));
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(OrgPartner $orgPartner): array
    {
        return [
            'hub'        => $orgPartner->partner->code.' ('.$orgPartner->partner->name.')',
            'drafts_to_submit' => PartnerShoppingListItem::where('org_partner_id', $orgPartner->id)
                ->where('state', ShoppingListItemStateEnum::DRAFT)
                ->count(),
            'open_lines' => PartnerShoppingListItem::where('org_partner_id', $orgPartner->id)
                ->whereIn('state', ShoppingListItemStateEnum::onPartnerBuyerList())
                ->with('orgStock:id,code,name')
                ->orderByDesc('id')
                ->limit(300)
                ->get()
                ->map(fn (PartnerShoppingListItem $item) => [
                    'sko'           => $item->orgStock->code,
                    'name'          => $item->orgStock->name,
                    'state'         => $item->state->value,
                    'quantity'      => (float) $item->quantity,
                    'priority'      => $item->priority?->value,
                    'notes'         => $item->notes,
                    'being_prepared' => $item->pre_picked_at || $item->job_order_id || $item->preparing_at,
                    'can_still_change' => $item->state === ShoppingListItemStateEnum::DRAFT || $item->isWaitingForPartner(),
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
            ]))->description('SKOs to put in the basket as drafts, up to 200. Omit to only show the list'),
            'sent_lines'   => $schema->array()->items($schema->object([
                'sko'      => $schema->string()->description('SKO code')->required(),
                'quantity' => $schema->number()->description('New SKO quantity of the line already sent')->required(),
            ]))->description('Users enrolled to place orders only, dangerous: change the quantity of lines already sent to the hub that it has not started. Needs accept ["change_submitted"]'),
            'submit'       => $schema->boolean()->description('Users enrolled to place orders only: send every draft in the basket to the hub, after the lines are saved'),
            'accept'       => $schema->array()->items($schema->string())->description('Warnings the user accepted after being told, e.g. ["change_submitted"]'),
            'request_text' => $schema->string()->description('The user\'s request, verbatim; required when writing'),
        ];
    }
}
