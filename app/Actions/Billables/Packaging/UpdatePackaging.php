<?php

/*
 * Author: Andi Ferdiawan
 * Created: Thu, 09 Jul 2026 17:00:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Billables\Packaging;

use App\Actions\Helpers\Media\SaveModelImage;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Packaging\PackagingStateEnum;
use App\Enums\Catalogue\Packaging\PackagingTypeEnum;
use App\Models\Billables\Packaging;
use App\Rules\IUnique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdatePackaging extends OrgAction
{
    use WithActionUpdate;

    private Packaging $packaging;

    public function handle(Packaging $packaging, array $modelData): Packaging
    {
        $image = Arr::pull($modelData, 'image');

        $packaging = $this->update($packaging, $modelData);

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
                'sometimes',
                'required',
                'max:64',
                'alpha_dash',
                new IUnique(
                    table: 'packagings',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],
                        ['column' => 'state', 'operator' => '!=', 'value' => PackagingStateEnum::DISCONTINUED->value],
                        ['column' => 'deleted_at', 'operator' => 'null'],
                        ['column' => 'id', 'value' => $this->packaging->id, 'operator' => '!='],
                    ]
                ),
            ],
            'family_code' => ['sometimes', 'required', 'max:64', 'alpha_dash'],
            'name'        => ['sometimes', 'required', 'max:250', 'string'],
            'type'        => ['sometimes', 'required', Rule::enum(PackagingTypeEnum::class)],
            'price'       => ['sometimes', 'required', 'numeric', 'min:0'],
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

    public function action(Packaging $packaging, array $modelData, int $hydratorsDelay = 0, bool $strict = true): Packaging
    {
        $this->hydratorsDelay = $hydratorsDelay;
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->packaging      = $packaging;

        $this->initialisationFromShop($packaging->shop, $modelData);

        return $this->handle($packaging, $this->validatedData);
    }

    public function asController(Packaging $packaging, ActionRequest $request): Packaging
    {
        $this->packaging = $packaging;
        $this->initialisationFromShop($packaging->shop, $request);

        return $this->handle($packaging, $this->validatedData);
    }

    public function htmlResponse(Packaging $packaging): RedirectResponse
    {
        return Redirect::route('grp.org.shops.show.billables.packagings.index', [
            'organisation' => $packaging->organisation->slug,
            'shop'         => $packaging->shop->slug,
        ])->with('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Packaging :code updated successfully.', ['code' => $packaging->code]),
        ]);
    }
}
