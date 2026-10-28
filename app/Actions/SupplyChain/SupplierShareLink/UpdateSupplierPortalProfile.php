<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierShareLink;

use App\Actions\Helpers\Media\SaveModelImage;
use App\Models\SupplyChain\SupplierPortalProfile;
use App\Models\SupplyChain\SupplierShareLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The person on the supplier page changes their own name, phone, photo or colours. Only their own profile, the one
 * the link and their cookie point to; the company's details go through the review form instead.
 */
class UpdateSupplierPortalProfile
{
    use AsAction;

    public const int THEMES = 10;

    /**
     * @param  array{name?: string|null, phone?: string|null, theme?: int, photo?: UploadedFile}  $modelData
     */
    public function handle(SupplierPortalProfile $profile, array $modelData): SupplierPortalProfile
    {
        if ($photo = Arr::pull($modelData, 'photo')) {
            SaveModelImage::run(
                model: $profile,
                imageData: [
                    'path'         => $photo->getPathName(),
                    'originalName' => $photo->getClientOriginalName(),
                    'extension'    => $photo->getClientOriginalExtension(),
                ],
                scope: 'avatar'
            );
        }

        $profile->update($modelData);

        return $profile;
    }

    public function asController(string $token, Request $request): RedirectResponse
    {
        $link = SupplierShareLink::findLiveByToken($token);
        abort_if($link->needsCode(), 403);

        $modelData = Validator::make($request->all(), [
            'name'  => ['sometimes', 'nullable', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:64', 'regex:/^\+?[0-9 ()-]{6,}$/'],
            'theme' => ['sometimes', 'integer', 'min:0', 'max:'.(self::THEMES - 1)],
            'photo' => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ])->validate();

        $this->handle(ResolveSupplierPortalProfile::make()->handle($link, $request), $modelData);

        return back();
    }
}
