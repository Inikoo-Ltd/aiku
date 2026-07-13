<?php

/*
 * Author: Andi Ferdiawan
 * Created: Fri, 10 Jul 2026 17:00:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Retina\SysAdmin;

use App\Actions\Helpers\Media\StoreMediaFromFile;
use App\Actions\RetinaAction;
use App\Enums\Catalogue\Leaflet\LeafletStateEnum;
use App\Enums\Catalogue\Packaging\PackagingStateEnum;
use App\Models\Billables\Leaflet;
use App\Models\Billables\ModelHasLeaflet;
use App\Models\Billables\Packaging;
use App\Models\CRM\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UploadRetinaLeaflet extends RetinaAction
{
    public function handle(Customer $customer, array $modelData): ModelHasLeaflet
    {
        return DB::transaction(function () use ($customer, $modelData) {
            $leaflet = Leaflet::where('shop_id', $this->shop->id)
                ->findOrFail(Arr::get($modelData, 'leaflet_id'));

            /** @var UploadedFile $file */
            $file = Arr::get($modelData, 'file');

            $media = StoreMediaFromFile::run(
                $customer,
                [
                    'path'         => $file->getPathName(),
                    'originalName' => $file->getClientOriginalName(),
                    'extension'    => $file->getClientOriginalExtension(),
                    'checksum'     => md5_file($file->getPathName()),
                ],
                'leaflets',
                'leaflet'
            );

            $state = Arr::get($modelData, 'active', false)
                ? LeafletStateEnum::ACTIVE
                : LeafletStateEnum::INACTIVE;

            $packagingIds = Packaging::where('shop_id', $this->shop->id)
                ->where('family_code', Arr::get($modelData, 'family_code'))
                ->where('state', PackagingStateEnum::ACTIVE)
                ->pluck('id');

            $existingRows = ModelHasLeaflet::where('model_type', 'Customer')
                ->where('model_id', $customer->id)
                ->where('shop_id', $this->shop->id)
                ->where('leaflet_id', $leaflet->id)
                ->whereIn('packaging_id', $packagingIds)
                ->get();

            if ($existingRows->isNotEmpty()) {
                ModelHasLeaflet::whereIn('id', $existingRows->pluck('id'))->update([
                    'media_id' => $media->id,
                    'state'    => $state,
                ]);

                return $existingRows->first()->refresh();
            }

            $rows = $packagingIds
                ->map(fn ($packagingId) => ModelHasLeaflet::create([
                    'group_id'        => $customer->group_id,
                    'organisation_id' => $customer->organisation_id,
                    'shop_id'         => $this->shop->id,
                    'model_type'      => 'Customer',
                    'model_id'        => $customer->id,
                    'leaflet_id'      => $leaflet->id,
                    'packaging_id'    => $packagingId,
                    'type'            => $leaflet->type,
                    'name'            => $leaflet->name,
                    'media_id'        => $media->id,
                    'state'           => $state,
                ]));

            return $rows->first();
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
            'file'        => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:20480'],
            'active'      => ['sometimes', 'boolean'],
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
            'description' => __('Leaflet :name uploaded successfully.', ['name' => $customerLeaflet->name]),
        ]);
    }
}
