<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 04 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock;
use App\Enums\SysAdmin\Authorisation\WarehousePermissionsEnum;
use App\Enums\SysAdmin\McpChange\McpChangeTypeEnum;
use App\Models\Inventory\Location;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\OrgStock;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

/**
 * Gated by can_use_mcp_locations on top of the stock edit permission the same change needs in the UI (INI-205).
 * It only associates a SKO with a location, with no quantity: stock is moved and counted by people.
 */
#[Description('Shows the warehouse locations of SKOs, or puts SKOs on locations of the same organisation. With skos it lists where each SKO is kept, with the quantity. With pairs (up to 200 {sko, location} items) and no confirm it previews what would be added, what is already there and what is refused (unknown SKO or location, a location code found in several warehouses: pass warehouse). With confirm=true it adds the SKOs that can be added, with no stock, and reports the refused ones. It never moves stock, changes a quantity or takes a SKO off a location. Show the user the preview and only confirm after they agreed in their own words, passing their request text. Every addition is logged in AI changes and can be reverted there while the location is still empty. Only for users enrolled to put SKOs on locations through their assistant.')]
class StockLocationsTool extends Tool
{
    use WithMcpPermissions;
    use WithMcpChangeLog;

    public function handle(Request $request): Response
    {
        $request->validate([
            'organisation'     => ['required', 'string'],
            'warehouse'        => ['sometimes', 'string'],
            'skos'             => ['sometimes', 'array', 'min:1', 'max:200'],
            'skos.*'           => ['string'],
            'pairs'            => ['sometimes', 'array', 'min:1', 'max:200'],
            'pairs.*.sko'      => ['required', 'string'],
            'pairs.*.location' => ['required', 'string'],
            'confirm'          => ['sometimes', 'boolean'],
            'request_text'     => ['required_if:confirm,true', 'string', 'max:4000'],
        ]);

        $user = $request->user();
        if (!$user?->can_use_mcp_locations) {
            return Response::error('Putting SKOs on locations is not enabled for this user. Do not retry; an administrator enrols it on the user\'s edit page.');
        }

        $identifier   = strtolower((string) $request->string('organisation'));
        $organisation = Organisation::where('group_id', $user->group_id)
            ->where(fn ($query) => $query->whereRaw('lower(code) = ?', [$identifier])->orWhereRaw('lower(slug) = ?', [$identifier]))
            ->first();
        if (!$organisation) {
            return Response::error("'$identifier' is not an organisation.");
        }

        setPermissionsTeamId($user->group_id);
        if (!$user->authTo(WarehousePermissionsEnum::getStockEditPermissionNames($organisation))) {
            return Response::error("Putting SKOs on locations in $organisation->code needs stock edit permission there, which this user does not have. Nothing was changed.");
        }

        if ($request->has('skos')) {
            return Response::json(['skos' => collect($request->get('skos'))->map(fn (string $code) => $this->skoLocations($organisation, $code))->all()]);
        }

        if (!$request->has('pairs')) {
            return Response::error('Pass skos to see where SKOs are kept, or pairs to put SKOs on locations.');
        }

        $plan = $this->plan($organisation, $request->get('pairs'), $request->get('warehouse'));

        if (!$request->boolean('confirm')) {
            return Response::json([
                'to_add'          => collect($plan['to_add'])->map(fn (array $pair) => ['sko' => $pair['sko'], 'location' => $pair['location']])->all(),
                'already_there'   => $plan['already_there'],
                'refused'         => $plan['refused'],
                'next'            => $plan['to_add'] ? 'Show this to the user; call again with confirm=true and their request text to add them.' : 'Nothing to add.',
            ]);
        }

        if (!$plan['to_add']) {
            return Response::json(['added' => [], 'already_there' => $plan['already_there'], 'refused' => $plan['refused']]);
        }

        $target = ['organisation_id' => $organisation->id, 'pairs' => $plan['to_add']];

        try {
            $this->recordChange(
                $request,
                McpChangeTypeEnum::STOCK_LOCATIONS,
                $organisation->code.': '.count($plan['to_add']).' SKOs put on locations',
                $target,
                fn () => DB::transaction(function () use ($plan) {
                    foreach ($plan['to_add'] as $pair) {
                        StoreLocationOrgStock::make()->action(OrgStock::findOrFail($pair['org_stock_id']), Location::findOrFail($pair['location_id']), []);
                    }
                })
            );
        } catch (ValidationException $exception) {
            return Response::error('Nothing was changed. '.implode(' ', $exception->validator->errors()->all()));
        }

        return Response::json([
            'added'         => collect($plan['to_add'])->map(fn (array $pair) => $pair['sko'].' @ '.$pair['location'])->all(),
            'already_there' => $plan['already_there'],
            'refused'       => $plan['refused'],
            'change_log_id' => $this->mcpChange?->id,
        ]);
    }

    private function skoLocations(Organisation $organisation, string $code): array
    {
        $orgStock = $this->findOrgStock($organisation, $code);
        if (!$orgStock) {
            return ['sko' => $code, 'error' => 'not a SKO of '.$organisation->code];
        }

        return [
            'sko'       => $orgStock->code,
            'locations' => LocationOrgStock::where('org_stock_id', $orgStock->id)->whereHas('location')->with('location.warehouse')->get()
                ->map(fn (LocationOrgStock $locationOrgStock) => [
                    'location'  => $locationOrgStock->location->code,
                    'warehouse' => $locationOrgStock->location->warehouse->code,
                    'quantity'  => (float) $locationOrgStock->quantity,
                    'type'      => $locationOrgStock->type?->value,
                ])->all(),
        ];
    }

    /**
     * @param  array<int, array{sko: string, location: string}>  $pairs
     *
     * @return array{to_add: array<int, array<string, mixed>>, already_there: array<int, string>, refused: array<int, string>}
     */
    private function plan(Organisation $organisation, array $pairs, ?string $warehouse): array
    {
        $plan = ['to_add' => [], 'already_there' => [], 'refused' => []];
        $seen = [];

        foreach ($pairs as $pair) {
            $orgStock = $this->findOrgStock($organisation, $pair['sko']);
            if (!$orgStock) {
                $plan['refused'][] = "{$pair['sko']}: not a SKO of $organisation->code";
                continue;
            }

            $locations = Location::where('organisation_id', $organisation->id)
                ->whereRaw('lower(code) = ?', [strtolower(trim($pair['location']))])
                ->when($warehouse, fn ($query) => $query->whereHas('warehouse', fn ($query) => $query->whereRaw('lower(code) = ?', [strtolower($warehouse)])->orWhereRaw('lower(slug) = ?', [strtolower($warehouse)])))
                ->with('warehouse')
                ->get();
            if ($locations->isEmpty()) {
                $plan['refused'][] = "$orgStock->code: there is no location {$pair['location']} in $organisation->code".($warehouse ? " warehouse $warehouse" : '');
                continue;
            }
            if ($locations->count() > 1) {
                $plan['refused'][] = "$orgStock->code: location {$pair['location']} is in several warehouses (".$locations->pluck('warehouse.code')->implode(', ').'): pass warehouse';
                continue;
            }

            $location = $locations->first();
            $key      = $orgStock->id.'-'.$location->id;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            if (LocationOrgStock::where('org_stock_id', $orgStock->id)->where('location_id', $location->id)->exists()) {
                $plan['already_there'][] = "$orgStock->code @ $location->code";
                continue;
            }

            $plan['to_add'][] = ['org_stock_id' => $orgStock->id, 'location_id' => $location->id, 'sko' => $orgStock->code, 'location' => $location->code];
        }

        return $plan;
    }

    private function findOrgStock(Organisation $organisation, string $code): ?OrgStock
    {
        return OrgStock::where('organisation_id', $organisation->id)->whereRaw('lower(code) = ?', [strtolower(trim($code))])->first();
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'organisation' => $schema->string()->description('Organisation code or slug, e.g. SK')->required(),
            'warehouse'    => $schema->string()->description('Warehouse code, only when a location code exists in several warehouses of the organisation'),
            'skos'         => $schema->array()->items($schema->string())->description('SKO codes to show the locations of'),
            'pairs'        => $schema->array()->items($schema->object([
                'sko'      => $schema->string()->required(),
                'location' => $schema->string()->required(),
            ]))->description('SKOs to put on locations: [{"sko": "EOKG-65", "location": "A-01-02"}]'),
            'confirm'      => $schema->boolean()->description('true to add them after the user saw the preview; omit to preview'),
            'request_text' => $schema->string()->description('The user\'s request, verbatim; required with confirm'),
        ];
    }
}
