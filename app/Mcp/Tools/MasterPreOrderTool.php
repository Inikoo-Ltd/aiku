<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\Masters\MasterAsset\UpdateMasterAsset;
use App\Actions\Ordering\PreOrder\GetProductPreOrder;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Enums\SysAdmin\McpChange\McpChangeTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

/**
 * HELP-4863. Pre-order is set on the master product and reaches every shop following it through the
 * normal master update, as a save on the master product edit page does. Gated like master prices:
 * can_use_mcp_prices on the user's account plus master edit permission.
 */
#[Description('Shows or switches pre-order on master products: back-order (a stock item customers can still order while it is out of stock, sent when the next delivery arrives) and made-to-order (not stocked, ordered from the supplier when a customer buys it; trade customers pay a deposit), with the optional lead time in days, deposit percentage and maximum quantity per order. Every shop following the master gets the change; pre-orders only show in shops that have pre-orders switched on, which the preview says per shop. Call it first without any setting, with a master family or product codes, and show the user the current settings and every shop product; then call it again with codes, the settings to change and request_text only after the user confirmed in their own words. Pass null to lead time, deposit or maximum quantity to clear it back to the default. Every change is logged and can be reverted with ai-changes-tool. Only for users enrolled to change prices through their assistant.')]
class MasterPreOrderTool extends Tool
{
    use WithMcpPermissions;
    use WithMcpChangeLog;

    public const int MAX_PRODUCTS = 100;

    public function handle(Request $request): Response
    {
        $request->validate([
            'master_shop'                  => ['required', 'string'],
            'family'                       => ['sometimes', 'string'],
            'codes'                        => ['sometimes', 'array', 'min:1', 'max:'.self::MAX_PRODUCTS],
            'codes.*'                      => ['string'],
            'is_back_order'                => ['sometimes', 'boolean'],
            'is_made_to_order'             => ['sometimes', 'boolean'],
            'pre_order_lead_time_days'     => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1000'],
            'pre_order_deposit_percentage' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'max_quantity_per_order'       => ['sometimes', 'nullable', 'integer', 'min:1'],
            'request_text'                 => ['sometimes', 'string', 'max:4000'],
        ]);

        $user = $request->user();
        if (!$user?->can_use_mcp_prices) {
            return Response::error('Changing pre-order is not enabled for this user. Do not retry; an administrator enrols it on the user\'s edit page (Change master prices).');
        }
        if (!$this->userCan($request, 'masters.edit')) {
            return Response::error('Pre-order is set on master products and needs master edit permission, which this user does not have. Nothing was changed.');
        }

        $given      = strtolower((string) $request->string('master_shop'));
        $masterShop = MasterShop::where('group_id', $user->group_id)
            ->where(fn ($query) => $query->whereRaw('lower(slug) = ?', [$given])->orWhereRaw('lower(code) = ?', [$given]))
            ->first();
        if (!$masterShop) {
            return Response::error("'$given' is not a master shop. Master shops: ".MasterShop::where('group_id', $user->group_id)->pluck('code')->implode(', ').'.');
        }

        $settings = collect(Product::PRE_ORDER_FIELDS)
            ->filter(fn (string $field) => array_key_exists($field, $request->all()))
            ->mapWithKeys(fn (string $field) => [$field => $request->get($field)])
            ->all();

        if (!$settings) {
            return $this->show($request, $masterShop);
        }

        if (!$request->has('codes')) {
            return Response::error('Give the master product codes to change. Nothing was changed.');
        }
        if (!$request->get('request_text')) {
            return Response::error('request_text is required to change pre-order: pass the user\'s confirmation in their own words. Nothing was changed.');
        }

        $codes        = collect($request->get('codes'))->map(fn ($code) => strtolower(trim($code)))->unique();
        $masterAssets = $this->masterProducts($masterShop)->whereIn(DB::raw('lower(code)'), $codes->all())->orderBy('id')->get();
        $missing      = $codes->diff($masterAssets->map(fn (MasterAsset $masterAsset) => strtolower($masterAsset->code)));
        if ($missing->isNotEmpty()) {
            return Response::error('Unknown master product codes in '.$masterShop->code.': '.$missing->implode(', ').'. Nothing was changed.');
        }

        try {
            DB::transaction(fn () => $this->recordChange(
                $request,
                McpChangeTypeEnum::MASTER_PRE_ORDER,
                'Pre-order in '.$masterShop->code.': '.$masterAssets->pluck('code')->implode(', ').' ('.collect($settings)->map(fn ($value, string $field) => $field.'='.json_encode($value))->implode(', ').')',
                ['master_asset_ids' => $masterAssets->pluck('id')->all()],
                function () use ($masterAssets, $settings) {
                    foreach ($masterAssets as $masterAsset) {
                        UpdateMasterAsset::make()->action($masterAsset, $settings);
                    }
                },
                ['master_shop' => $masterShop->code]
            ));
        } catch (ValidationException $exception) {
            return Response::error(implode(' ', $exception->validator->errors()->all()).' Nothing was changed.');
        }

        return Response::json([
            'changed'       => (bool) $this->mcpChange,
            'change_log_id' => $this->mcpChange?->id,
            'master_shop'   => $masterShop->code,
            'products'      => $masterAssets->map(fn (MasterAsset $masterAsset) => $this->describe($masterAsset->refresh()))->all(),
            'note'          => 'The shop products follow the master now; their web pages refresh in the next minutes.',
        ]);
    }

    private function show(Request $request, MasterShop $masterShop): Response
    {
        $query = $this->masterProducts($masterShop);

        if ($request->has('codes')) {
            $query->whereIn(DB::raw('lower(code)'), array_map(fn ($code) => strtolower(trim($code)), $request->get('codes')));
        } elseif ($request->has('family')) {
            $masterFamily = MasterProductCategory::where('master_shop_id', $masterShop->id)
                ->where('type', MasterProductCategoryTypeEnum::FAMILY)
                ->whereRaw('lower(code) = ?', [strtolower((string) $request->string('family'))])
                ->first();
            if (!$masterFamily) {
                return Response::error("'{$request->string('family')}' is not a master family in {$masterShop->code}.");
            }
            $query->where('master_family_id', $masterFamily->id);
        } else {
            return Response::error('Give a master family code or a list of master product codes.');
        }

        return Response::json([
            'master_shop' => $masterShop->code,
            'products'    => $query->orderBy('code')->limit(self::MAX_PRODUCTS)->get()->map(fn (MasterAsset $masterAsset) => $this->describe($masterAsset))->all(),
        ]);
    }

    private function describe(MasterAsset $masterAsset): array
    {
        $preOrder = GetProductPreOrder::make();

        return [
            'code'     => $masterAsset->code,
            'name'     => $masterAsset->name,
            ...$this->settings($masterAsset),
            'products' => $masterAsset->products()->with('shop')->orderBy('shop_id')->get()->map(fn (Product $product) => [
                'shop'                   => $product->shop->code,
                'code'                   => $product->code,
                'state'                  => $product->state?->value,
                'stock'                  => $product->available_quantity,
                'shop_has_pre_orders_on' => $product->shop->hasPreOrders(),
                'sold_as_pre_order'      => $preOrder->type($product)?->value,
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(MasterAsset $masterAsset): array
    {
        return [
            'is_back_order'                => (bool) $masterAsset->is_back_order,
            'is_made_to_order'             => (bool) $masterAsset->is_made_to_order,
            'pre_order_lead_time_days'     => $masterAsset->pre_order_lead_time_days,
            'pre_order_deposit_percentage' => $masterAsset->pre_order_deposit_percentage !== null ? (float) $masterAsset->pre_order_deposit_percentage : null,
            'max_quantity_per_order'       => $masterAsset->max_quantity_per_order,
        ];
    }

    private function masterProducts(MasterShop $masterShop)
    {
        return MasterAsset::where('master_shop_id', $masterShop->id)
            ->where('type', MasterAssetTypeEnum::PRODUCT)
            ->where('status', true);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'master_shop'                  => $schema->string()->description('Master shop slug or code, e.g. aw')->required(),
            'family'                       => $schema->string()->description('Master family code: shows every product in it. Only to show'),
            'codes'                        => $schema->array()->items($schema->string())->description('Master product codes, up to 100; required to change'),
            'is_back_order'                => $schema->boolean()->description('true = back-order: orderable while out of stock, sent when the next delivery arrives. Omit to leave it as it is'),
            'is_made_to_order'             => $schema->boolean()->description('true = made-to-order: not stocked, ordered from the supplier when a customer buys it. Omit to leave it as it is'),
            'pre_order_lead_time_days'     => $schema->integer()->description('Lead time in days; null = use the supplier\'s pre-order lead time. Omit to leave it as it is'),
            'pre_order_deposit_percentage' => $schema->number()->description('Made-to-order deposit for trade customers, 0-100; null = the shop\'s deposit. Omit to leave it as it is'),
            'max_quantity_per_order'       => $schema->integer()->description('Most a customer can order at once; null = no limit. Omit to leave it as it is'),
            'request_text'                 => $schema->string()->description('The user\'s confirmation, verbatim, kept in the change log; required when changing'),
        ];
    }
}
