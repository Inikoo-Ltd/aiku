<?php

/*
 * Author: Andi Ferdiawan
 * Created: Thu, 09 Jul 2026 10:30:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Retina\UI\SysAdmin;

use App\Actions\RetinaAction;
use App\Enums\Catalogue\Packaging\PackagingStateEnum;
use App\Http\Resources\Helpers\ImageResource;
use App\Models\Billables\Packaging;
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
                'packagingOptions' => $this->getPackagingOptions(),
                'currencyCode'     => $this->shop->currency->code,
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
