<?php

/*
 * Author Louis Perez
 * Created on 28-10-2026-10h-30m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\Design;

use App\Actions\Goods\ProductDevelopment\RecordProductDevelopmentActivity;
use App\Actions\Goods\ProductDevelopment\Recipe\ApproveProductDevelopmentComponent;
use App\Actions\Goods\ProductDevelopment\Recipe\StoreProductDevelopmentComponent;
use App\Actions\Goods\TradeUnit\StoreTradeUnit;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentArtworkStatusEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentKindEnum;
use App\Models\Goods\ProductDevelopmentArtwork;
use App\Models\Goods\TradeUnit;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Turns the approved artwork of a printed item (box, label, tag) into its own trade unit, so Purchasing can set up
 * its stock and printer and order it as usual. On an in-house development it also becomes an approved component of the SKU, so it shows on the Buyer's list.
 */
class CreateProductDevelopmentArtworkTradeUnit extends OrgAction
{
    use WithProductDevelopmentAuthorisation;

    private ProductDevelopmentArtwork $artwork;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $productDevelopment = $this->artwork->productDevelopment;

        return $this->canViewProductDevelopment($request->user(), $productDevelopment)
            && $this->productDevelopmentAccess()->canEdit($request->user(), $productDevelopment);
    }

    public static function suggestedCode(ProductDevelopmentArtwork $artwork): string
    {
        return Str::limit($artwork->item->tradeUnit->code.'-'.Str::upper(Str::slug($artwork->section->name, '')), 64, '');
    }

    public static function suggestedName(ProductDevelopmentArtwork $artwork): string
    {
        return $artwork->section->name.' – '.$artwork->item->tradeUnit->name;
    }

    public function handle(ProductDevelopmentArtwork $artwork, User $user, array $modelData): TradeUnit
    {
        $productDevelopment = $artwork->productDevelopment;

        return DB::transaction(function () use ($artwork, $user, $modelData, $productDevelopment) {
            $tradeUnit = StoreTradeUnit::make()->action($productDevelopment->group, [
                'code' => $modelData['code'],
                'name' => $modelData['name'],
            ]);
            $artwork->update(['trade_unit_id' => $tradeUnit->id]);

            if ($productDevelopment->kind === ProductDevelopmentKindEnum::RECIPE) {
                $component = StoreProductDevelopmentComponent::make()->action($artwork->item, [
                    'trade_unit_id' => $tradeUnit->id,
                    'quantity'      => $modelData['quantity'],
                    'note'          => __(':section artwork', ['section' => $artwork->section->name]),
                ]);
                ApproveProductDevelopmentComponent::make()->action($component, $user, ['approved' => true]);
            }

            RecordProductDevelopmentActivity::record($productDevelopment, RecordProductDevelopmentActivity::ARTWORK_TRADE_UNIT_CREATED, [], [
                'trade_unit' => $tradeUnit->code,
                'artwork'    => ProductDevelopmentArtworks::label($artwork),
            ]);

            return $tradeUnit;
        });
    }

    public function rules(): array
    {
        return [
            'code'     => ['required', 'string', 'max:64'],
            'name'     => ['required', 'string', 'max:255'],
            'quantity' => [$this->artwork->productDevelopment->kind === ProductDevelopmentKindEnum::RECIPE ? 'required' : 'nullable', 'numeric', 'gt:0', 'max:9999999'],
        ];
    }

    public function getValidationMessages(): array
    {
        return [
            'quantity.required' => __('Enter how many are needed to start production'),
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->artwork->status !== ProductDevelopmentArtworkStatusEnum::APPROVED) {
            $validator->errors()->add('code', __('Approve the artwork first'));
        } elseif ($this->artwork->trade_unit_id) {
            $validator->errors()->add('code', __('This artwork already has its trade unit'));
        } elseif (TradeUnit::where('group_id', $this->artwork->productDevelopment->group_id)->whereRaw('lower(code) = ?', [Str::lower((string) $this->get('code'))])->exists()) {
            $validator->errors()->add('code', __('A trade unit with this code already exists'));
        }
    }

    public function asController(ProductDevelopmentArtwork $productDevelopmentArtwork, ActionRequest $request): TradeUnit
    {
        $this->artwork = $productDevelopmentArtwork;
        $this->initialisationFromGroup($productDevelopmentArtwork->productDevelopment->group, $request);

        return $this->handle($productDevelopmentArtwork, $request->user(), $this->validatedData);
    }

    public function action(ProductDevelopmentArtwork $artwork, User $user, array $modelData): TradeUnit
    {
        $this->asAction = true;
        $this->artwork  = $artwork;
        $this->initialisationFromGroup($artwork->productDevelopment->group, $modelData);

        return $this->handle($artwork, $user, Arr::only($this->validatedData, ['code', 'name', 'quantity']));
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
