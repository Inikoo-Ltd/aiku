<?php

/*
 * Author: Andi Ferdiawan
 * Created: Fri, 10 Jul 2026 18:00:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Retina\SysAdmin;

use App\Actions\Helpers\Media\StoreMediaFromFile;
use App\Actions\RetinaAction;
use App\Enums\Catalogue\Packaging\PackagingStateEnum;
use App\Models\Billables\ModelHasLeaflet;
use App\Models\Billables\Packaging;
use App\Models\CRM\Customer;
use App\Models\Helpers\Media;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class UpdateRetinaLeaflet extends RetinaAction
{
    public function handle(Customer $customer, array $modelData): ModelHasLeaflet
    {
        return DB::transaction(function () use ($customer, $modelData) {
            $packagingIds = Packaging::where('shop_id', $this->shop->id)
                ->where('family_code', Arr::get($modelData, 'family_code'))
                ->where('state', PackagingStateEnum::ACTIVE)
                ->pluck('id');

            $rows = ModelHasLeaflet::where('model_type', 'Customer')
                ->where('model_id', $customer->id)
                ->where('shop_id', $this->shop->id)
                ->where('leaflet_id', Arr::get($modelData, 'leaflet_id'))
                ->whereIn('packaging_id', $packagingIds)
                ->get();

            if ($rows->isEmpty()) {
                throw ValidationException::withMessages([
                    'leaflet_id' => __('No uploaded leaflet found for this packaging.'),
                ]);
            }

            $mediaId = $rows->first()->media_id;

            if ($file = Arr::get($modelData, 'file')) {
                /** @var UploadedFile $file */
                $media = StoreMediaFromFile::run(
                    $customer,
                    [
                        'path'         => $file->getPathName(),
                        'originalName' => Arr::get($modelData, 'name', $file->getClientOriginalName()),
                        'extension'    => $file->getClientOriginalExtension(),
                        'checksum'     => md5_file($file->getPathName()),
                    ],
                    'leaflets',
                    'leaflet'
                );

                $oldMediaId = $mediaId;
                $mediaId    = $media->id;

                ModelHasLeaflet::whereIn('id', $rows->pluck('id'))->update(['media_id' => $mediaId]);

                if ($oldMediaId && !ModelHasLeaflet::where('media_id', $oldMediaId)->exists()) {
                    Media::find($oldMediaId)?->delete();
                }
            } elseif (Arr::has($modelData, 'name') && $mediaId) {
                Media::where('id', $mediaId)->update(['name' => Arr::get($modelData, 'name')]);
            }

            return $rows->first()->refresh();
        });
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->is_root;
    }

    public function rules(): array
    {
        return [
            'leaflet_id'  => [
                'required',
                'integer',
                Rule::exists('leaflets', 'id')->where('shop_id', $this->shop->id),
            ],
            'family_code' => [
                'required',
                'string',
                Rule::exists('packagings', 'family_code')
                    ->where('shop_id', $this->shop->id)
                    ->where('state', PackagingStateEnum::ACTIVE->value),
            ],
            'name'        => ['sometimes', 'required', 'string', 'max:250'],
            'file'        => ['sometimes', 'required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:20480'],
        ];
    }

    public function asController(ActionRequest $request): ModelHasLeaflet
    {
        $this->initialisation($request);

        return $this->handle($this->customer, $this->validatedData);
    }

    public function htmlResponse(ModelHasLeaflet $customerLeaflet): RedirectResponse
    {
        return Redirect::back()->with('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Leaflet :name updated successfully.', ['name' => $customerLeaflet->name]),
        ]);
    }
}
