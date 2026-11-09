<?php

/*
 * Author: Rifqi <rifqitaufiqurrohman1@gmail.com>
 * Created: Fri, 16 Oct 2026
 * Copyright (c) 2026
 */

namespace App\Actions\Masters\MasterAsset;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Models\Masters\MasterAsset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rules\File;
use Lorisleiva\Actions\ActionRequest;

/**
 * Saves the "Edit product" page of a master product: the texts, price and barcode on the master
 * product, and the shipping and return texts on its master family for all its products. The
 * physical data (weights, dimensions, material, origin) belong to the trade unit, shared by the
 * dropshipping and wholesale masters made of it, so they are only shown here and edited on the
 * trade unit.
 */
class UpdateMasterProductInformation extends OrgAction
{
    use WithMastersEditAuthorisation;

    public function handle(MasterAsset $masterAsset, array $modelData): MasterAsset
    {
        $masterData = Arr::only($modelData, ['code', 'name', 'price', 'description', 'description_extra', 'barcode', 'meta_description', 'seo_image', 'seo_image_url', 'seo_image_alt']);
        if ($masterData !== []) {
            UpdateMasterAsset::make()->action($masterAsset, $masterData);
        }

        $masterFamily = $masterAsset->masterFamily;
        if (Arr::has($modelData, 'shared') && $masterFamily) {
            $masterFamily->update([
                'data' => [
                    ...($masterFamily->data ?? []),
                    'shared_information' => [
                        'shipping'      => Arr::get($modelData, 'shared.shipping'),
                        'return_policy' => Arr::get($modelData, 'shared.return_policy'),
                    ],
                ],
            ]);
        }

        return $masterAsset->refresh();
    }

    public function rules(): array
    {
        return [
            'code'                  => ['sometimes', 'required', 'string', 'max:64', 'regex:/^[A-Za-z0-9-]+$/'],
            'name'                  => ['sometimes', 'required', 'string', 'max:250'],
            'price'                 => ['sometimes', 'required', 'numeric', 'gt:0'],
            'description'           => ['sometimes', 'required', 'string', 'max:1500'],
            'description_extra'     => ['sometimes', 'nullable', 'string', 'max:65500'],
            'barcode'               => ['sometimes', 'nullable', 'regex:/^(\d{8}|\d{12}|\d{13})$/'],
            'meta_description'      => ['sometimes', 'nullable', 'string', 'max:500'],
            'seo_image'             => ['sometimes', 'nullable', File::image()->max(12 * 1024)],
            'seo_image_url'         => ['sometimes', 'nullable', 'string', 'max:2048'],
            'seo_image_alt'         => ['sometimes', 'nullable', 'string', 'max:255'],
            'shared'                => ['sometimes', 'array:shipping,return_policy'],
            'shared.*'              => ['nullable', 'string', 'max:65500'],
        ];
    }

    public function getValidationMessages(): array
    {
        return [
            'code.regex'          => __('Letters, numbers and hyphens only'),
            'price.gt'            => __('The price must be more than 0'),
            'barcode.regex'       => __('A barcode has 8, 12 or 13 digits'),
        ];
    }

    public function asController(MasterAsset $masterAsset, ActionRequest $request): MasterAsset
    {
        $this->initialisationFromGroup($masterAsset->group, $request);

        return $this->handle($masterAsset, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
