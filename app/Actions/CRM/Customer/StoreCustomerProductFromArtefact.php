<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Oct 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer;

use App\Actions\Catalogue\Product\StoreProduct;
use App\Actions\Goods\Stock\StoreStock;
use App\Actions\Inventory\OrgStock\StoreOrgStock;
use App\Actions\OrgAction;
use App\Actions\Production\Artefact\UpdateArtefact;
use App\Enums\Production\Artefact\ArtefactStateEnum;
use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use App\Models\Goods\Stock;
use App\Models\Production\Artefact;
use App\Rules\AlphaDashDot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * A custom piece is designed in production as an artefact first; CRM then turns it into
 * something the customer can order: its stock, trade unit and SKO, and a product sold only to them.
 */
class StoreCustomerProductFromArtefact extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Customer $customer, array $modelData): Product
    {
        return DB::transaction(function () use ($customer, $modelData) {
            $artefact = $this->lockArtefactWithoutStock($modelData['artefact_id']);

            $stock = StoreStock::make()->action($artefact->group, [
                'code'       => $artefact->code,
                'name'       => $artefact->name ?? $artefact->code,
                'units'      => 1,
                'trade_unit' => ['description' => $artefact->name ?? $artefact->code],
            ]);
            $tradeUnitId = $stock->tradeUnits()->value('trade_units.id');
            $orgStock    = StoreOrgStock::make()->action($artefact->organisation, $stock);

            UpdateArtefact::make()->action($artefact, [
                'trade_unit_id' => $tradeUnitId,
                'org_stock_id'  => $orgStock->id,
            ]);

            return StoreProduct::make()->action($customer->shop, [
                'code'                      => $modelData['code'],
                'name'                      => $modelData['name'],
                'price'                     => $modelData['price'],
                'unit'                      => 'piece',
                'units'                     => $modelData['units'],
                'is_main'                   => true,
                'exclusive_for_customer_id' => $customer->id,
                'trade_units'               => [['id' => $tradeUnitId, 'quantity' => $modelData['units']]],
            ]);
        });
    }

    /**
     * An artefact whose code already names a stock is an old part never linked to it, not a new piece:
     * a stock shared by other organisations and products must not become one customer's product.
     */
    private function lockArtefactWithoutStock(int $artefactId): Artefact
    {
        /** @var Artefact $artefact */
        $artefact = Artefact::whereKey($artefactId)->lockForUpdate()->firstOrFail();

        $stockExists = Stock::where('group_id', $artefact->group_id)->whereRaw('lower(code) = ?', [strtolower($artefact->code)])->exists();
        if ($artefact->trade_unit_id || $artefact->org_stock_id || $stockExists) {
            throw ValidationException::withMessages([
                'artefact_id' => __('Artefact :code already has a stock, link it to its trade unit in production instead.', ['code' => $artefact->code]),
            ]);
        }

        return $artefact;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("crm.{$this->shop->id}.edit");
    }

    public function rules(): array
    {
        return [
            'artefact_id' => [
                'required',
                'integer',
                Rule::exists('artefacts', 'id')
                    ->where('organisation_id', $this->organisation->id)
                    ->whereNull('trade_unit_id')
                    ->whereNull('deleted_at'),
            ],
            'code'        => ['required', 'string', 'max:64', new AlphaDashDot()],
            'name'        => ['required', 'string', 'max:250'],
            'price'       => ['required', 'numeric', 'min:0'],
            'units'       => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * New artefacts of an organisation, with no stock yet: the ones a custom product can be made from.
     */
    public static function newArtefacts(int $organisationId): Builder
    {
        return Artefact::where('organisation_id', $organisationId)
            ->whereNull('trade_unit_id')
            ->whereNull('org_stock_id')
            ->where('state', '!=', ArtefactStateEnum::DISCONTINUED)
            ->whereNotExists(fn ($query) => $query->from('stocks')
                ->whereColumn('stocks.group_id', 'artefacts.group_id')
                ->whereRaw('lower(stocks.code) = lower(artefacts.code)'));
    }

    public static function artefactOptions(Customer $customer): Collection
    {
        return self::newArtefacts($customer->organisation_id)->orderBy('code')->get(['id', 'code', 'name']);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }

    /**
     * @throws \Throwable
     */
    public function action(Customer $customer, array $modelData): Product
    {
        $this->asAction = true;
        $this->initialisationFromShop($customer->shop, $modelData);

        return $this->handle($customer, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Customer $customer, ActionRequest $request): Product
    {
        $this->initialisationFromShop($customer->shop, $request);

        return $this->handle($customer, $this->validatedData);
    }
}
