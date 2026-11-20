<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\Inventory\OrgStock\UpdateOrgStock;
use App\Enums\SysAdmin\Authorisation\WarehousePermissionsEnum;
use App\Enums\SysAdmin\McpChange\McpChangeTypeEnum;
use App\Models\Inventory\OrgStock;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Shows or switches the production flags of SKOs (organisation stock) in one organisation: on demand (its products never go out of stock and stay orderable on the websites with zero stock), made in-house (ready to make: our production makes it) and made fresh (made only for orders already placed, never kept on the shelf). Call it first without any flag to show the user the current flags and every product using each SKO, per shop; then call it again with the flags to change and request_text only after the user confirmed in their own words. Every change is logged and can be reverted with ai-changes-tool. Only for users enrolled to discontinue SKOs through their assistant.')]
class OrgStockFlagsTool extends AikuOrgStockDiscontinueTool
{
    use WithMcpChangeLog;

    public const FLAGS = ['is_on_demand', 'is_made_in_house', 'is_fresh'];

    public function handle(Request $request): Response
    {
        $request->validate([
            'organisation'     => ['required', 'string'],
            'codes'            => ['required', 'array', 'min:1', 'max:50'],
            'codes.*'          => ['string'],
            'is_on_demand'     => ['sometimes', 'boolean'],
            'is_made_in_house' => ['sometimes', 'boolean'],
            'is_fresh'         => ['sometimes', 'boolean'],
            'request_text'     => ['sometimes', 'string', 'max:4000'],
        ]);

        $denied = $this->denied($request);
        if ($denied) {
            return $denied;
        }

        $organisation = $this->organisationByCode($request);
        if (!$organisation) {
            return $this->organisationNotFoundError($request);
        }

        [$orgStocks, $missing] = $this->orgStocksByCodes($organisation, $request->get('codes'));

        $flags = collect(self::FLAGS)
            ->filter(fn (string $flag) => $request->has($flag))
            ->mapWithKeys(fn (string $flag) => [$flag => $request->boolean($flag)])
            ->all();

        if (!$flags) {
            return Response::json([
                'organisation' => $organisation->code,
                'not_found'    => $missing,
                'skos'         => $orgStocks->map(fn (OrgStock $orgStock) => $this->describeOrgStock($orgStock))->all(),
            ]);
        }

        if ($missing) {
            return Response::error('Unknown SKO codes in '.$organisation->code.': '.implode(', ', $missing).'. Nothing was changed.');
        }

        if (!$request->get('request_text')) {
            return Response::error('request_text is required to change flags: pass the user\'s confirmation in their own words. Nothing was changed.');
        }

        if (!$request->user()->authTo(WarehousePermissionsEnum::getStockEditPermissionNames($organisation))) {
            return Response::error('Changing SKOs in '.$organisation->code.' needs the stock edit permission. Do not retry.');
        }

        try {
            DB::transaction(fn () => $this->recordChange(
                $request,
                McpChangeTypeEnum::ORG_STOCK_FLAGS,
                'SKOs '.$orgStocks->pluck('code')->implode(', ').' ('.$organisation->code.'): '.collect($flags)->map(fn (bool $value, string $flag) => $flag.'='.($value ? 'yes' : 'no'))->implode(', '),
                ['organisation_id' => $organisation->id, 'org_stock_ids' => $orgStocks->pluck('id')->all()],
                function () use ($orgStocks, $flags) {
                    foreach ($orgStocks as $orgStock) {
                        $changes = array_filter($flags, fn (bool $value, string $flag) => (bool) $orgStock->{$flag} !== $value, ARRAY_FILTER_USE_BOTH);
                        if ($changes) {
                            UpdateOrgStock::make()->action($orgStock, $changes);
                        }
                    }
                },
                ['organisation' => $organisation->code]
            ));
        } catch (ValidationException $exception) {
            return Response::error(implode(' ', $exception->validator->errors()->all()).' Nothing was changed.');
        }

        return Response::json([
            'organisation'  => $organisation->code,
            'skos'          => $orgStocks->map(fn (OrgStock $orgStock) => $this->describeOrgStock($orgStock->refresh()))->all(),
            'change_log_id' => $this->mcpChange?->id,
        ]);
    }

    private function describeOrgStock(OrgStock $orgStock): array
    {
        return [
            'code'          => $orgStock->code,
            'name'          => $orgStock->name,
            'state'         => $orgStock->state->value,
            'stock'         => (float) $orgStock->quantity_in_locations,
            ...Arr::mapWithKeys(self::FLAGS, fn (string $flag) => [$flag => (bool) $orgStock->{$flag}]),
            'products'      => $orgStock->products()->with('shop:id,code')->orderBy('shop_id')->get()->map(fn ($product) => [
                'shop'               => $product->shop->code,
                'code'               => $product->code,
                'status'             => $product->status?->value,
                'is_on_demand'       => (bool) $product->is_on_demand,
                'available_quantity' => $product->available_quantity,
            ])->all(),
        ];
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'organisation'     => $schema->string()->description('Organisation code or slug the SKO codes belong to, e.g. sk')->required(),
            'codes'            => $schema->array()->items($schema->string())->description('SKO codes, up to 50')->required(),
            'is_on_demand'     => $schema->boolean()->description('true = on demand (orderable with zero stock), false = sold from stock only. Omit to leave it as it is'),
            'is_made_in_house' => $schema->boolean()->description('true = made in-house, ready to make in our production. Omit to leave it as it is'),
            'is_fresh'         => $schema->boolean()->description('true = made fresh, only for orders already placed. Omit to leave it as it is'),
            'request_text'     => $schema->string()->description('The user\'s confirmation, verbatim, kept in the change log; required when changing a flag'),
        ];
    }
}
