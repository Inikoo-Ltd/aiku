<?php

/*
 * Author: Andi Ferdiawan
 * Created: Thu, 09 Jul 2026 10:30:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Retina\UI\SysAdmin;

use App\Actions\RetinaAction;
use App\Enums\Catalogue\Leaflet\LeafletStateEnum;
use App\Enums\Catalogue\Packaging\PackagingStateEnum;
use App\Http\Resources\Helpers\ImageResource;
use App\Models\Billables\Leaflet;
use App\Models\Billables\ModelHasLeaflet;
use App\Models\Billables\Packaging;
use App\Models\CRM\CustomerHasPackaging;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowRetinaPackagingPreferences extends RetinaAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->is_root;
    }

    public function asController(ActionRequest $request): Response
    {
        $this->initialisation($request);

        return $this->handle($request);
    }

    public function handle(ActionRequest $request): Response
    {
        $title = __('Packaging preferences');

        return Inertia::render(
            'SysAdmin/RetinaPackagingPreferences',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => $title,
                'pageHead'    => [
                    'title' => $title,
                    'icon'  => [
                        'icon'  => ['fal', 'fa-gift'],
                        'title' => $title
                    ],
                ],
                'packagingOptions'    => $this->getPackagingOptions(),
                'leafletOptions'      => $this->getLeafletOptions(),
                'selectedFamilyCode'  => $this->getSelectedFamilyCode(),
                'personalisedMessage' => $this->getPersonalisedMessage(),
                'selectedLeafletIds'  => $this->getSelectedLeafletIds(),
                'customerLeaflets'    => $this->getCustomerLeaflets(),
                'currencyCode'        => $this->shop->currency->code,
                'updateRoute'         => [
                    'name' => 'retina.sysadmin.packaging-preferences.update',
                ],
            ]
        );
    }

    private function getPackagingOptions(): array
    {
        return Packaging::where('shop_id', $this->shop->id)
            ->where('state', PackagingStateEnum::ACTIVE)
            ->with('image')
            ->orderBy('position')
            ->orderBy('price')
            ->get()
            ->groupBy('family_code')
            ->map(function (Collection $packagings, string $familyCode) {
                $image = $packagings->first(fn (Packaging $packaging) => $packaging->image)?->image;

                return [
                    'family_code' => $familyCode,
                    'type'        => $packagings->first()->type->value,
                    'label'       => $this->getFamilyLabel($packagings),
                    'sizes'       => $packagings->count() > 1
                        ? __('Various sizes')
                        : $this->getDimensionsLabel($packagings->first()),
                    'price_min'   => (float) $packagings->min('price'),
                    'price_max'   => (float) $packagings->max('price'),
                    'image'       => $image ? ImageResource::make($image)->resolve() : null,
                ];
            })
            ->sortBy('price_min')
            ->values()
            ->all();
    }

    /** @return array<int, array{id: int, label: string, type: string, type_label: string, price: float, family_codes: array<int, string>}> */
    private function getLeafletOptions(): array
    {
        return Leaflet::where('shop_id', $this->shop->id)
            ->where('state', LeafletStateEnum::ACTIVE)
            ->orderBy('price')
            ->orderBy('name')
            ->get()
            ->map(fn (Leaflet $leaflet) => [
                'id'           => $leaflet->id,
                'label'        => $leaflet->name,
                'type'         => $leaflet->type->value,
                'type_label'   => $leaflet->type->labels()[$leaflet->type->value],
                'price'        => (float) $leaflet->price,
                'family_codes' => $leaflet->family_codes ?? [],
            ])->all();
    }

    private function customerPackagingQuery(): Builder
    {
        return CustomerHasPackaging::where('customer_id', $this->customer->id)
            ->whereHas('packaging', fn (Builder $query) => $query->where('shop_id', $this->shop->id));
    }

    private function getSelectedFamilyCode(): ?string
    {
        return $this->customerPackagingQuery()
            ->with('packaging')
            ->first()
            ?->packaging
            ?->family_code;
    }

    private function getPersonalisedMessage(): ?string
    {
        return $this->customerPackagingQuery()->first()?->personalised_message;
    }

    private function customerLeafletQuery(): Builder
    {
        return ModelHasLeaflet::where('shop_id', $this->shop->id)
            ->where('model_type', 'Customer')
            ->where('model_id', $this->customer->id);
    }

    /** @return array<int, int> */
    private function getSelectedLeafletIds(): array
    {
        return $this->customerLeafletQuery()
            ->where('state', LeafletStateEnum::ACTIVE)
            ->pluck('leaflet_id')
            ->unique()
            ->values()
            ->all();
    }

    /** @return array<int, array{id: int, name: string, type_label: string, size: string|null, uploaded_at: string|null, state: string, state_label: string}> */
    private function getCustomerLeaflets(): array
    {
        return $this->customerLeafletQuery()
            ->whereNotNull('media_id')
            ->with('media')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ModelHasLeaflet $customerLeaflet) => [
                'id'          => $customerLeaflet->id,
                'name'        => $customerLeaflet->media?->file_name ?? $customerLeaflet->name,
                'type_label'  => $customerLeaflet->type->labels()[$customerLeaflet->type->value],
                'size'        => $customerLeaflet->media
                    ? round($customerLeaflet->media->size / 1048576, 1).' MB'
                    : null,
                'uploaded_at' => $customerLeaflet->created_at?->format('d/m/Y'),
                'state'       => $customerLeaflet->state->value,
                'state_label' => $customerLeaflet->state->labels()[$customerLeaflet->state->value],
            ])->all();
    }

    private function getFamilyLabel(Collection $packagings): string
    {
        $names  = $packagings->pluck('name')->all();
        $prefix = array_shift($names);

        foreach ($names as $name) {
            while ($prefix !== '' && !str_starts_with($name, $prefix)) {
                $prefix = substr($prefix, 0, -1);
            }
        }

        $prefix = trim($prefix, " -–");

        return strlen($prefix) >= 3 ? $prefix : $packagings->first()->name;
    }

    private function getDimensionsLabel(Packaging $packaging): ?string
    {
        if (!$packaging->width || !$packaging->height || !$packaging->depth) {
            return null;
        }

        return "{$packaging->width} × {$packaging->height} × {$packaging->depth} mm";
    }

    public function getBreadcrumbs(): array
    {
        return
            array_merge(
                ShowRetinaSysAdminDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'retina.sysadmin.packaging-preferences.show'
                            ],
                            'label' => __('Packaging preferences'),
                        ]
                    ]
                ]
            );
    }
}
