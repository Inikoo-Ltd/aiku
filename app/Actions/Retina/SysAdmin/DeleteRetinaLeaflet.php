<?php

/*
 * Author: Andi Ferdiawan
 * Created: Fri, 10 Jul 2026 18:00:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Retina\SysAdmin;

use App\Actions\RetinaAction;
use App\Enums\Catalogue\Packaging\PackagingStateEnum;
use App\Models\Billables\ModelHasLeaflet;
use App\Models\Billables\Packaging;
use App\Models\CRM\Customer;
use App\Models\Helpers\Media;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class DeleteRetinaLeaflet extends RetinaAction
{
    public function handle(Customer $customer, array $modelData): void
    {
        DB::transaction(function () use ($customer, $modelData) {
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

            $mediaIds = $rows->pluck('media_id')->filter()->unique();

            ModelHasLeaflet::whereIn('id', $rows->pluck('id'))->update(['media_id' => null]);

            foreach ($mediaIds as $mediaId) {
                if (!ModelHasLeaflet::where('media_id', $mediaId)->exists()) {
                    Media::find($mediaId)?->delete();
                }
            }
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
        ];
    }

    public function asController(ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($this->customer, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back()->with('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Leaflet file deleted successfully.'),
        ]);
    }
}
