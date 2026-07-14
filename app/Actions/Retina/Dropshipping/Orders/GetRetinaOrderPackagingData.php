<?php

/*
 * Author: Andi Ferdiawan
 * Created: Fri, 10 Jul 2026 21:00:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Enums\Catalogue\Leaflet\LeafletStateEnum;
use App\Enums\Catalogue\Packaging\PackagingStateEnum;
use App\Models\Billables\Leaflet;
use App\Models\Billables\ModelHasLeaflet;
use App\Models\Billables\Packaging;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\CRM\CustomerHasPackaging;
use App\Models\Ordering\Order;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class GetRetinaOrderPackagingData
{
    use AsAction;

    /**
     * @return array{
     *     packagingOptions: array<int, array{value: int, label: string, price: float, family_code: string|null}>,
     *     selectedPackaging: int|null,
     *     leafletOptions: array<int, array{id: int, label: string, price: float, family_codes: array<int, string>}>,
     *     defaultLeafletsByFamily: array<string, array<int, int>>,
     *     personalisedMessage: string,
     *     customerLeaflets: array<int, array{id: int, leaflet_id: int, family_code: string|null, name: string, mime_type: string|null, meta: string|null, state: string, state_label: string}>,
     * }
     */
    public function handle(Shop $shop, Customer $customer, Order $order): array
    {
        $packagings = Packaging::where('shop_id', $shop->id)
            ->where('state', PackagingStateEnum::ACTIVE)
            ->orderBy('position')
            ->orderBy('price')
            ->get();

        $packagingOptions = $packagings
            ->map(fn (Packaging $packaging) => [
                'value'       => $packaging->id,
                'label'       => $packaging->name,
                'price'       => (float) $packaging->price,
                'family_code' => $packaging->family_code,
            ])
            ->values()
            ->all();

        // The order's own selection (override) takes precedence over the customer default.
        $orderPackagingId = $order->packaging_id;
        $orderLeafletIds  = $order->insert_types ?? [];

        $selectedPackaging       = $orderPackagingId ?: $this->getSelectedPackagingId($shop, $customer);
        $defaultLeafletsByFamily = $this->getDefaultLeafletsByFamily($shop, $customer);

        if ($orderPackagingId) {
            $orderFamily = $packagings->firstWhere('id', $orderPackagingId)?->family_code;
            if ($orderFamily) {
                $defaultLeafletsByFamily[$orderFamily] = array_map('intval', $orderLeafletIds);
            }
        }

        return [
            'packagingOptions'        => $packagingOptions,
            'selectedPackaging'       => $selectedPackaging ? (int) $selectedPackaging : null,
            'leafletOptions'          => $this->getLeafletOptions($shop),
            'defaultLeafletsByFamily' => $defaultLeafletsByFamily,
            'personalisedMessage'     => $this->getPersonalisedMessage($shop, $customer),
            'customerLeaflets'        => $this->getCustomerLeaflets($shop, $customer),
        ];
    }

    private function getSelectedPackagingId(Shop $shop, Customer $customer): ?int
    {
        // Only the customer's own preference — no standard fallback. When the customer
        // has not set a preference, the panel shows the "Select packaging" placeholder.
        return CustomerHasPackaging::where('customer_id', $customer->id)
            ->whereHas('packaging', fn ($query) => $query->where('shop_id', $shop->id)->where('state', PackagingStateEnum::ACTIVE))
            ->with('packaging')
            ->get()
            ->sortBy(fn (CustomerHasPackaging $row) => (float) $row->packaging?->price)
            ->first()
            ?->packaging
            ?->id;
    }

    /** @return array<int, array{id: int, label: string, price: float, family_codes: array<int, string>}> */
    private function getLeafletOptions(Shop $shop): array
    {
        return Leaflet::where('shop_id', $shop->id)
            ->where('state', LeafletStateEnum::ACTIVE)
            ->orderBy('price')
            ->orderBy('name')
            ->get()
            ->map(fn (Leaflet $leaflet) => [
                'id'           => $leaflet->id,
                'label'        => $leaflet->name,
                'price'        => (float) $leaflet->price,
                'family_codes' => $leaflet->family_codes ?? [],
            ])->all();
    }

    /** @return array<string, array<int, int>> */
    private function getDefaultLeafletsByFamily(Shop $shop, Customer $customer): array
    {
        return ModelHasLeaflet::where('model_type', 'Customer')
            ->where('model_id', $customer->id)
            ->where('shop_id', $shop->id)
            ->where('state', LeafletStateEnum::ACTIVE)
            ->with('packaging')
            ->get()
            ->groupBy(fn (ModelHasLeaflet $row) => $row->packaging?->family_code)
            ->reject(fn ($rows, $familyCode) => $familyCode === '' || $familyCode === null)
            ->map(fn (Collection $rows) => $rows->pluck('leaflet_id')->unique()->values()->all())
            ->all();
    }

    private function getPersonalisedMessage(Shop $shop, Customer $customer): string
    {
        return (string) CustomerHasPackaging::where('customer_id', $customer->id)
            ->whereHas('packaging', fn ($query) => $query->where('shop_id', $shop->id))
            ->first()
            ?->personalised_message;
    }

    /** @return array<int, array{id: int, leaflet_id: int, family_code: string|null, name: string, mime_type: string|null, meta: string|null, state: string, state_label: string}> */
    private function getCustomerLeaflets(Shop $shop, Customer $customer): array
    {
        return ModelHasLeaflet::where('model_type', 'Customer')
            ->where('model_id', $customer->id)
            ->where('shop_id', $shop->id)
            ->whereNotNull('media_id')
            ->with(['media', 'packaging'])
            ->orderByDesc('updated_at')
            ->get()
            ->unique(fn (ModelHasLeaflet $row) => $row->leaflet_id.'|'.$row->packaging?->family_code)
            ->map(fn (ModelHasLeaflet $row) => [
                'id'          => $row->id,
                'leaflet_id'  => $row->leaflet_id,
                'family_code' => $row->packaging?->family_code,
                'name'        => $row->media?->name ?? $row->name,
                'mime_type'   => $row->media?->mime_type,
                'meta'        => $row->created_at
                    ? __('Uploaded :date', ['date' => $row->created_at->format('d/m/Y')])
                    : null,
                'state'       => $row->state->value,
                'state_label' => $row->state->labels()[$row->state->value],
            ])
            ->values()
            ->all();
    }
}
