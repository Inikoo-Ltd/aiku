<?php

/*
 * Author: Andi Ferdiawan
 * Created: Thu, 09 Jul 2026 16:00:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Billables\Packaging;

use App\Actions\OrgAction;
use App\Enums\Catalogue\Packaging\PackagingStateEnum;
use App\Enums\Catalogue\Packaging\PackagingTypeEnum;
use App\Models\Catalogue\Shop;
use App\Rules\IUnique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StorePackagingFamily extends OrgAction
{
    public function handle(Shop $shop, array $modelData): Collection
    {
        return DB::transaction(function () use ($shop, $modelData) {
            $packagings = collect();

            foreach (Arr::get($modelData, 'packagings', []) as $packagingData) {
                $packagings->push(
                    StorePackaging::make()->action($shop, [
                        'family_code' => Arr::get($modelData, 'family_code'),
                        'type'        => Arr::get($modelData, 'type'),
                        ...Arr::only($packagingData, ['code', 'name', 'price', 'width', 'height', 'depth', 'image', 'state']),
                    ])
                );
            }

            return $packagings;
        });
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("products.{$this->shop->id}.edit");
    }

    public function rules(): array
    {
        return [
            'family_code'          => ['required', 'max:64', 'alpha_dash'],
            'type'                 => ['required', Rule::enum(PackagingTypeEnum::class)],
            'packagings'           => ['required', 'array', 'min:1'],
            'packagings.*.code'    => [
                'required',
                'max:64',
                'alpha_dash',
                'distinct',
                new IUnique(
                    table: 'packagings',
                    column: 'code',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],
                        ['column' => 'state', 'operator' => '!=', 'value' => PackagingStateEnum::DISCONTINUED->value],
                        ['column' => 'deleted_at', 'operator' => 'null'],
                    ]
                ),
            ],
            'packagings.*.name'    => ['required', 'max:250', 'string'],
            'packagings.*.price'   => ['required', 'numeric', 'min:0'],
            'packagings.*.width'   => ['sometimes', 'nullable', 'integer', 'min:0'],
            'packagings.*.height'  => ['sometimes', 'nullable', 'integer', 'min:0'],
            'packagings.*.depth'   => ['sometimes', 'nullable', 'integer', 'min:0'],
            'packagings.*.image'   => ['sometimes', 'nullable', 'mimes:jpg,jpeg,png,gif,webp', 'max:10240'],
            'packagings.*.state'   => ['sometimes', 'required', Rule::enum(PackagingStateEnum::class)],
        ];
    }

    public function action(Shop $shop, array $modelData, int $hydratorsDelay = 0, bool $strict = true): Collection
    {
        $this->hydratorsDelay = $hydratorsDelay;
        $this->asAction       = true;
        $this->strict         = $strict;

        $this->initialisationFromShop($shop, $modelData);

        return $this->handle($shop, $this->validatedData);
    }

    public function asController(Shop $shop, ActionRequest $request): Collection
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $this->validatedData);
    }

    public function htmlResponse(Collection $packagings): RedirectResponse
    {
        $packaging = $packagings->first();

        return Redirect::route('grp.org.shops.show.billables.packagings.index', [
            'organisation' => $packaging->organisation->slug,
            'shop'         => $packaging->shop->slug,
        ])->with('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => trans_choice('{1} :count packaging created successfully.|[2,*] :count packagings created successfully.', $packagings->count(), ['count' => $packagings->count()]),
        ]);
    }
}
