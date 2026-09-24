<?php

/*
 * Author: Andi Ferdiawan
 * Created: Fri, 10 Jul 2026 16:00:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Retina\SysAdmin;

use App\Actions\RetinaAction;
use App\Enums\Catalogue\Leaflet\LeafletStateEnum;
use App\Enums\Catalogue\Leaflet\LeafletTypeEnum;
use App\Enums\Catalogue\Packaging\PackagingStateEnum;
use App\Models\Billables\Leaflet;
use App\Models\Billables\ModelHasLeaflet;
use App\Models\Billables\Packaging;
use App\Models\CRM\Customer;
use App\Models\CRM\CustomerHasPackaging;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class UpdateRetinaPackagingPreferences extends RetinaAction
{
    public function handle(Customer $customer, array $modelData): Customer
    {
        DB::transaction(function () use ($customer, $modelData) {
            $packagingIds = Packaging::where('shop_id', $this->shop->id)
                ->where('family_code', Arr::get($modelData, 'family_code'))
                ->where('state', PackagingStateEnum::ACTIVE)
                ->pluck('id');

            CustomerHasPackaging::where('customer_id', $customer->id)
                ->whereNotIn('packaging_id', $packagingIds)
                ->delete();

            foreach ($packagingIds as $packagingId) {
                CustomerHasPackaging::updateOrCreate(
                    [
                        'customer_id'  => $customer->id,
                        'packaging_id' => $packagingId,
                    ],
                    [
                        'personalised_message' => Arr::get($modelData, 'personalised_message'),
                    ]
                );
            }

            $checkedLeafletIds = collect(Arr::get($modelData, 'leaflet_ids', []));

            ModelHasLeaflet::where('model_type', 'Customer')
                ->where('model_id', $customer->id)
                ->where('shop_id', $this->shop->id)
                ->where(function ($query) use ($checkedLeafletIds, $packagingIds) {
                    $query->whereNotIn('leaflet_id', $checkedLeafletIds)
                        ->orWhereNotIn('packaging_id', $packagingIds);
                })
                ->update(['state' => LeafletStateEnum::INACTIVE]);

            $leaflets = Leaflet::where('shop_id', $this->shop->id)
                ->whereIn('id', $checkedLeafletIds)
                ->get()
                ->keyBy('id');

            foreach ($checkedLeafletIds as $leafletId) {
                $leaflet = $leaflets->get($leafletId);
                if (!$leaflet) {
                    continue;
                }

                foreach ($packagingIds as $packagingId) {
                    ModelHasLeaflet::updateOrCreate(
                        [
                            'model_type'   => 'Customer',
                            'model_id'     => $customer->id,
                            'leaflet_id'   => $leaflet->id,
                            'packaging_id' => $packagingId,
                        ],
                        [
                            'group_id'        => $customer->group_id,
                            'organisation_id' => $customer->organisation_id,
                            'shop_id'         => $this->shop->id,
                            'type'            => $leaflet->type,
                            'name'            => $leaflet->name,
                            'state'           => LeafletStateEnum::ACTIVE,
                        ]
                    );
                }
            }
        });

        return $customer;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->is_root
            && $request->user()->customer?->shop?->hasPackagingAndInserts();
    }

    public function rules(): array
    {
        return [
            'family_code'          => [
                'required',
                'string',
                Rule::exists('packagings', 'family_code')
                    ->where('shop_id', $this->shop->id)
                    ->where('state', PackagingStateEnum::ACTIVE->value),
            ],
            'leaflet_ids'          => ['present', 'array'],
            'leaflet_ids.*'        => [
                'integer',
                Rule::exists('leaflets', 'id')->where('shop_id', $this->shop->id),
            ],
            'personalised_message' => ['sometimes', 'nullable', 'string', 'max:200'],
        ];
    }

    /**
     * Every ticked insert has to be printable before it can be saved, except a personalised
     * message: that one is typed into the message box, never uploaded as a file.
     */
    public function afterValidator(Validator $validator): void
    {
        $checkedLeafletIds = Leaflet::whereIn('id', collect($this->get('leaflet_ids', []))->filter())
            ->where('type', '!=', LeafletTypeEnum::PERSONALISED_MESSAGE)
            ->pluck('id');

        if ($checkedLeafletIds->isEmpty()) {
            return;
        }

        $packagingIds = Packaging::where('shop_id', $this->shop->id)
            ->where('family_code', $this->get('family_code'))
            ->where('state', PackagingStateEnum::ACTIVE)
            ->pluck('id');

        if ($packagingIds->isEmpty()) {
            return;
        }

        $coveredByLeaflet = ModelHasLeaflet::where('model_type', 'Customer')
            ->where('model_id', $this->customer->id)
            ->where('shop_id', $this->shop->id)
            ->whereIn('leaflet_id', $checkedLeafletIds)
            ->whereIn('packaging_id', $packagingIds)
            ->whereNotNull('media_id')
            ->get()
            ->groupBy('leaflet_id')
            ->map->count();

        $missingIds = $checkedLeafletIds->reject(
            fn (int $leafletId) => ($coveredByLeaflet[$leafletId] ?? 0) >= $packagingIds->count()
        );

        if ($missingIds->isEmpty()) {
            return;
        }

        $names = Leaflet::whereIn('id', $missingIds)->orderBy('name')->pluck('name');

        $validator->errors()->add(
            'leaflet_ids',
            __('Upload a file for :names before saving. Every insert you tick has to be printable.', [
                'names' => $names->implode(', '),
            ])
        );
    }

    public function asController(ActionRequest $request): Customer
    {
        $this->initialisation($request);

        return $this->handle($this->customer, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back()->with('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Packaging preferences saved successfully.'),
        ]);
    }
}
