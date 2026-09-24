<?php

/*
 * Author: Andi Ferdiawan
 * Created: Thu, 09 Jul 2026 10:00:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Billables\Packaging;

use App\Actions\Helpers\Media\SaveModelImage;
use App\Actions\OrgAction;
use App\Enums\Catalogue\Packaging\PackagingStateEnum;
use App\Enums\Catalogue\Packaging\PackagingTypeEnum;
use App\Models\Billables\Packaging;
use App\Models\Catalogue\Shop;
use App\Rules\IUnique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StorePackaging extends OrgAction
{
    public function handle(Shop $shop, array $modelData): Packaging
    {
        if (!Arr::has($modelData, 'state')) {
            data_set($modelData, 'state', PackagingStateEnum::IN_PROCESS);
        }

        data_set($modelData, 'group_id', $shop->group_id);
        data_set($modelData, 'organisation_id', $shop->organisation_id);
        data_set($modelData, 'shop_id', $shop->id);
        data_set($modelData, 'currency_id', $shop->currency_id);

        $image = Arr::pull($modelData, 'image');

        $packaging = Packaging::create($modelData);

        if ($image) {
            SaveModelImage::run(
                model: $packaging,
                imageData: [
                    'path'         => $image->getPathName(),
                    'originalName' => $image->getClientOriginalName(),
                    'extension'    => $image->getClientOriginalExtension(),
                ]
            );
        }

        $packaging->refresh();

        return $packaging;
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
            'code'        => [
                'required',
                'max:64',
                'alpha_dash',
                new IUnique(
                    table: 'packagings',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],
                        ['column' => 'state', 'operator' => '!=', 'value' => PackagingStateEnum::DISCONTINUED->value],
                        ['column' => 'deleted_at', 'operator' => 'null'],
                    ]
                ),
            ],
            'family_code' => ['required', 'max:64', 'alpha_dash'],
            'name'        => ['required', 'max:250', 'string'],
            'type'        => ['required', Rule::enum(PackagingTypeEnum::class)],
            'price'       => ['required', 'numeric', 'min:0'],
            'width'       => ['sometimes', 'nullable', 'integer', 'min:0'],
            'height'      => ['sometimes', 'nullable', 'integer', 'min:0'],
            'depth'       => ['sometimes', 'nullable', 'integer', 'min:0'],
            'box_id'      => [
                'sometimes',
                'nullable',
                Rule::exists('boxes', 'id')->where('group_id', $this->shop->group_id),
            ],
            'image'       => ['sometimes', 'nullable', 'mimes:jpg,jpeg,png,gif,webp', 'max:10240'],
            'state'       => ['sometimes', 'required', Rule::enum(PackagingStateEnum::class)],
            'position'    => ['sometimes', 'integer', 'min:0'],
            'data'        => ['sometimes', 'array'],
        ];
    }

    public function action(Shop $shop, array $modelData, int $hydratorsDelay = 0, bool $strict = true): Packaging
    {
        $this->hydratorsDelay = $hydratorsDelay;
        $this->asAction       = true;
        $this->strict         = $strict;

        $this->initialisationFromShop($shop, $modelData);

        return $this->handle($shop, $this->validatedData);
    }

    public function asController(Shop $shop, ActionRequest $request): Packaging
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $this->validatedData);
    }

    public function htmlResponse(Packaging $packaging): RedirectResponse
    {
        return Redirect::route('grp.org.shops.show.billables.packagings.index', [
            'organisation' => $packaging->organisation->slug,
            'shop'         => $packaging->shop->slug,
        ])->with('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Packaging :code created successfully.', ['code' => $packaging->code]),
        ]);
    }
}
