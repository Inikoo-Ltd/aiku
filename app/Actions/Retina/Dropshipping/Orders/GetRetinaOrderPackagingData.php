<?php

/*
 * Author: Andi Ferdiawan
 * Created: Fri, 10 Jul 2026 21:00:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\Ordering\Order\GetOrderInsertsWithoutArtwork;
use App\Actions\Traits\WithPackagingFamily;
use App\Enums\Catalogue\Leaflet\LeafletStateEnum;
use App\Enums\Catalogue\Leaflet\LeafletTypeEnum;
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
    use WithPackagingFamily;

    /**
     * @return array{
     *     packagingOptions: array<int, array{value: int, label: string, price: float, price_max: float, sizes: string|null, family_code: string|null}>,
     *     selectedPackaging: int|null,
     *     leafletOptions: array<int, array{id: int, label: string, type: string, price: float, family_codes: array<int, string>}>,
     *     defaultLeafletsByFamily: array<string, array<int, int>>,
     *     personalisedMessage: string,
     *     customerLeaflets: array<int, array{id: int, leaflet_id: int, family_code: string|null, name: string, mime_type: string|null, meta: string|null, state: string, state_label: string}>,
     * }
     */
    public function handle(Shop $shop, Customer $customer, Order $order): array
    {
        if (!$shop->hasPackagingAndInserts()) {
            return [
                'packagingOptions'        => [],
                'selectedPackaging'       => null,
                'leafletOptions'          => [],
                'defaultLeafletsByFamily' => [],
                'personalisedMessage'     => '',
                'customerLeaflets'        => [],
                'insertsWithoutArtwork'   => [],
                'personalisedMessageLeafletIds' => [],
            ];
        }

        $packagings = Packaging::where('shop_id', $shop->id)
            ->where('state', PackagingStateEnum::ACTIVE)
            ->orderBy('position')
            ->orderBy('price')
            ->get();

        // One row per family, mirroring the packaging preferences page: the customer picks the
        // packaging they want, the warehouse picks the size of it that fits the order.
        $families = $packagings->groupBy('family_code');

        $packagingOptions = $families
            ->map(fn (Collection $family) => [
                'value'       => $this->representativePackaging($family)->id,
                'label'       => $this->packagingFamilyLabel($family),
                'price'       => (float) $family->min('price'),
                'price_max'   => (float) $family->max('price'),
                'sizes'       => $this->packagingSizesLabel($family),
                'family_code' => $family->first()->family_code,
            ])
            ->sortBy('price')
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

        $selectedFamily = $selectedPackaging
            ? $packagings->firstWhere('id', $selectedPackaging)?->family_code
            : null;

        if ($selectedFamily && $families->has($selectedFamily)) {
            $selectedPackaging = $this->representativePackaging($families->get($selectedFamily))->id;
        }

        return [
            'packagingOptions'        => $packagingOptions,
            'selectedPackaging'       => $selectedPackaging ? (int) $selectedPackaging : null,
            'leafletOptions'          => $this->getLeafletOptions($shop),
            'defaultLeafletsByFamily' => $defaultLeafletsByFamily,
            'personalisedMessage'     => $this->getPersonalisedMessage($shop, $customer),
            'customerLeaflets'        => $this->getCustomerLeaflets($shop, $customer),


            'insertsWithoutArtwork'   => GetOrderInsertsWithoutArtwork::run($order),

            'personalisedMessageLeafletIds' => $this->getPersonalisedMessageLeafletIds($shop),
        ];
    }

    /**
     * Inserts that carry the customer's own message rather than an uploaded file. They are
     * driven by the message box, never by an upload.
     *
     * @return array<int, int>
     */
    private function getPersonalisedMessageLeafletIds(Shop $shop): array
    {
        return Leaflet::where('shop_id', $shop->id)
            ->where('type', LeafletTypeEnum::PERSONALISED_MESSAGE)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * The customer's own preference, else the shop's default. The default exists so an order
     * still ships in something the shop chose rather than in nothing at all.
     */
    private function getSelectedPackagingId(Shop $shop, Customer $customer): ?int
    {
        $preferred = CustomerHasPackaging::where('customer_id', $customer->id)
            ->whereHas('packaging', fn ($query) => $query->where('shop_id', $shop->id)->where('state', PackagingStateEnum::ACTIVE))
            ->with('packaging')
            ->get()
            ->sortBy(fn (CustomerHasPackaging $row) => (float) $row->packaging?->price)
            ->first()
            ?->packaging
            ?->id;

        return $preferred ?? $shop->defaultPackaging()?->id;
    }

    /** @return array<int, array{id: int, label: string, type: string, price: float, family_codes: array<int, string>}> */
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
                'type'         => $leaflet->type->value,
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
